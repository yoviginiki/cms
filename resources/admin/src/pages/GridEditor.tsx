import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutGrid, Plus, Minus, MousePointer, Eraser, Trash2,
  Settings, Palette, Columns, Smartphone, Monitor, Eye, ChevronDown, ChevronRight,
  Undo2, Redo2, Sparkles, AlertTriangle, Check, X, ArrowUp, ArrowDown,
} from 'lucide-react';
import { grids, categories as categoriesApi, publishing } from '@/lib/api';

type PositionType = 'canvas' | 'menu' | 'query' | 'fixed' | 'widget' | 'static';
type RightTab = 'grid-settings' | 'area-config';

interface Position {
  area_name: string;
  label: string;
  type: PositionType;
  config_json: Record<string, unknown>;
  scope: string;
  is_overridable: boolean;
  mobile_order: number;
  min_height?: string;
  align_self?: string;
  justify_self?: string;
  max_width?: string;
  overflow?: string;
  background_json?: Record<string, string>;
  padding_json?: Record<string, string>;
  border_json?: Record<string, string>;
  shadow?: string;
  css_class?: string;
  full_bleed?: boolean;
}

const TYPE_COLORS: Record<string, string> = {
  canvas: '#34d399', menu: '#60a5fa', query: '#c084fc',
  fixed: '#94a3b8', widget: '#fbbf24', static: '#fb923c', '': '#6b7280',
};
const TYPE_LABELS: Record<string, string> = {
  canvas: 'Блоково съдържание (per page)', menu: 'Навигационно меню', query: 'Динамичен списък постове',
  fixed: 'Фиксирано (еднакво навсякъде)', widget: 'Уиджет колона', static: 'Авто-генерирано',
};

// Shared control classes (match cms-admin dark theme)
const INPUT_CLS = 'w-full px-2.5 py-1.5 bg-base-200/60 border border-base-300 rounded-lg text-xs text-base-content placeholder:text-base-content/30 focus:outline-none focus:border-primary transition-colors';
const CHIP_CLS = 'px-2 py-0.5 text-[10px] rounded-md border border-base-300 text-base-content/60 hover:border-primary/60 hover:text-primary transition-colors';

// ─── Column presets ───
const COL_PRESETS = [
  { label: '1 колона', cols: ['1fr'] },
  { label: '2 равни', cols: ['1fr', '1fr'] },
  { label: '3 равни', cols: ['1fr', '1fr', '1fr'] },
  { label: '4 равни', cols: ['1fr', '1fr', '1fr', '1fr'] },
  { label: '⅓ + ⅔', cols: ['1fr', '2fr'] },
  { label: '⅔ + ⅓', cols: ['2fr', '1fr'] },
  { label: '¼ + ¾', cols: ['1fr', '3fr'] },
  { label: '¾ + ¼', cols: ['3fr', '1fr'] },
  { label: '¼ + ½ + ¼', cols: ['1fr', '2fr', '1fr'] },
  { label: '250px + auto', cols: ['250px', '1fr'] },
  { label: 'auto + 300px', cols: ['1fr', '300px'] },
  { label: '200px + auto + 300px', cols: ['200px', '1fr', '300px'] },
];

// ─── Alignment options ───
const ALIGN_OPTIONS = [
  { value: 'stretch', label: 'Stretch (запълва)' },
  { value: 'start', label: 'Start (горе/ляво)' },
  { value: 'center', label: 'Center (центрирано)' },
  { value: 'end', label: 'End (долу/дясно)' },
];

const SHADOW_PRESETS = [
  { label: 'Без', value: '' },
  { label: 'Лека', value: '0 1px 3px rgba(0,0,0,0.1)' },
  { label: 'Средна', value: '0 4px 6px rgba(0,0,0,0.1)' },
  { label: 'Тежка', value: '0 10px 25px rgba(0,0,0,0.15)' },
  { label: 'Мека', value: '0 2px 15px rgba(0,0,0,0.08)' },
];

// ─── Area name suggestions + default type per name ───
const NAME_SUGGESTIONS = ['header', 'nav', 'hero', 'content', 'sidebar', 'banner', 'gallery', 'cta', 'related', 'footer'];
const NAME_TYPE_MAP: Record<string, PositionType> = {
  nav: 'menu', menu: 'menu', sidebar: 'widget', breadcrumb: 'static', related: 'query',
};

// ─── Full-layout templates ───
interface LayoutTemplate {
  id: string; label: string; desc: string;
  colSizes: string[]; rowSizes: string[]; cells: string[][];
  types: Record<string, PositionType>;
}
const LAYOUT_TEMPLATES: LayoutTemplate[] = [
  {
    id: 'blog', label: 'Класически блог', desc: 'Header, съдържание + сайдбар, footer',
    colSizes: ['1fr', '340px'], rowSizes: ['auto', 'auto', 'auto'],
    cells: [['header', 'header'], ['content', 'sidebar'], ['footer', 'footer']],
    types: { header: 'menu', content: 'canvas', sidebar: 'widget', footer: 'menu' },
  },
  {
    id: 'holy-grail', label: 'Holy Grail', desc: 'Header, две странични колони, footer',
    colSizes: ['220px', '1fr', '300px'], rowSizes: ['auto', 'auto', 'auto'],
    cells: [['header', 'header', 'header'], ['left', 'content', 'right'], ['footer', 'footer', 'footer']],
    types: { header: 'menu', left: 'widget', content: 'canvas', right: 'widget', footer: 'menu' },
  },
  {
    id: 'landing', label: 'Лендинг секции', desc: 'Hero на цял екран + секции',
    colSizes: ['1fr'], rowSizes: ['100vh', 'auto', 'auto', 'auto'],
    cells: [['hero'], ['features'], ['cta'], ['footer']],
    types: { hero: 'canvas', features: 'canvas', cta: 'canvas', footer: 'menu' },
  },
  {
    id: 'magazine', label: 'Списание', desc: 'Featured лента + пост мрежа + сайдбар',
    colSizes: ['1fr', '1fr', '1fr'], rowSizes: ['auto', 'auto', 'auto', 'auto'],
    cells: [
      ['header', 'header', 'header'], ['featured', 'featured', 'featured'],
      ['posts', 'posts', 'sidebar'], ['footer', 'footer', 'footer'],
    ],
    types: { header: 'menu', featured: 'query', posts: 'query', sidebar: 'widget', footer: 'menu' },
  },
  {
    id: 'single-post', label: 'Единичен пост', desc: 'Тясна колона за четене + related',
    colSizes: ['1fr', 'minmax(auto, 720px)', '1fr'], rowSizes: ['auto', 'auto', 'auto', 'auto'],
    cells: [
      ['header', 'header', 'header'], ['.', 'content', '.'],
      ['related', 'related', 'related'], ['footer', 'footer', 'footer'],
    ],
    types: { header: 'menu', content: 'canvas', related: 'query', footer: 'menu' },
  },
  {
    id: 'empty', label: 'Празен грид', desc: 'Чисто платно 4 × 5',
    colSizes: ['1fr', '1fr', '1fr', '1fr'], rowSizes: ['auto', 'auto', 'auto', 'auto', 'auto'],
    cells: Array.from({ length: 5 }, () => Array(4).fill('.')),
    types: {},
  },
];

// ─── Mini layout thumbnail ───
function LayoutThumb({ cells, types }: { cells: string[][]; types: Record<string, PositionType> }) {
  const cols = cells[0]?.length || 1;
  return (
    <div className="grid gap-px w-16 shrink-0 rounded overflow-hidden border border-base-300 bg-base-300"
      style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
      {cells.flatMap((row, ri) => row.map((cell, ci) => (
        <div key={`${ri}-${ci}`} className="h-2"
          style={{ backgroundColor: cell === '.' ? 'oklch(0.17 0.01 260)' : TYPE_COLORS[types[cell] || 'canvas'] + '99' }} />
      )))}
    </div>
  );
}

// ─── Collapsible section ───
function Section({ title, icon, children, defaultOpen = false, hint }: {
  title: string; icon?: React.ReactNode; children: React.ReactNode; defaultOpen?: boolean; hint?: string;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border border-base-300 rounded-lg overflow-hidden">
      <button onClick={() => setOpen(!open)}
        className="w-full flex items-center gap-2 px-3 py-2.5 bg-base-200/60 hover:bg-base-200 transition-colors text-left">
        {open ? <ChevronDown size={14} className="text-base-content/40" /> : <ChevronRight size={14} className="text-base-content/40" />}
        {icon}
        <span className="text-xs font-semibold text-base-content/80 flex-1">{title}</span>
      </button>
      {hint && open && <p className="px-3 py-1.5 text-[10px] text-base-content/40 bg-base-200/40 border-b border-base-300">{hint}</p>}
      {open && <div className="p-3 space-y-3">{children}</div>}
    </div>
  );
}

// ─── Small labeled input ───
function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-[11px] font-medium text-base-content/50 mb-1">{label}</label>
      {children}
      {hint && <p className="text-[10px] text-base-content/40 mt-0.5">{hint}</p>}
    </div>
  );
}
function Input({ value, onChange, placeholder, className = '' }: {
  value: string; onChange: (v: string) => void; placeholder?: string; className?: string;
}) {
  return (
    <input value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
      className={`${INPUT_CLS} ${className}`} />
  );
}
function Select({ value, onChange, options, className = '' }: {
  value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; className?: string;
}) {
  return (
    <select value={value} onChange={e => onChange(e.target.value)}
      className={`${INPUT_CLS} bg-base-200 ${className}`}>
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  );
}

// ─── Display helpers: keep the editor grid stable even with exotic CSS values ───
function displayCol(s: string): string {
  const t = s.trim();
  if (t === 'auto') return 'minmax(64px, auto)';
  if (/^\d+(\.\d+)?(fr|px|%|em|rem|vw|vh)$/.test(t)) return t;
  if (/^minmax\(.+\)$/.test(t)) return t;
  return '1fr';
}
function displayRowHeight(s: string): number {
  const t = s.trim();
  if (t.endsWith('fr')) return Math.min(160, 112 * (parseFloat(t) || 1));
  if (t.endsWith('px')) return Math.max(48, Math.min(220, parseFloat(t) || 64));
  if (t.endsWith('vh')) return 120;
  return 64; // auto & everything else
}

interface Snapshot {
  cells: string[][]; colSizes: string[]; rowSizes: string[]; positions: Position[];
}
interface AreaInfo {
  name: string; r1: number; c1: number; r2: number; c2: number; count: number; valid: boolean;
}

// ═══════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════
export default function GridEditor() {
  const { siteId = '', gridId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isDirty, setIsDirty] = useState(false);
  const [name, setName] = useState('');
  const [colSizes, setColSizes] = useState<string[]>([]);
  const [rowSizes, setRowSizes] = useState<string[]>([]);
  const [gapX, setGapX] = useState('16px');
  const [gapY, setGapY] = useState('12px');
  const [containerWidth, setContainerWidth] = useState('1200px');
  const [containerPadding, setContainerPadding] = useState('0 24px');
  const [minHeight, setMinHeight] = useState('');
  const [alignItems, setAlignItems] = useState('stretch');
  const [justifyItems, setJustifyItems] = useState('stretch');
  const [overflowX, setOverflowX] = useState('visible');
  const [layoutMode, setLayoutMode] = useState('default');
  const [bgJson, setBgJson] = useState<Record<string, string>>({});
  const [fullBleed, setFullBleed] = useState(false);
  const [breakpointsJson, setBreakpointsJson] = useState<Record<string, any>>({});
  const [cells, setCells] = useState<string[][]>([]);
  const [positions, setPositions] = useState<Position[]>([]);
  const [selectedArea, setSelectedArea] = useState<string | null>(null);
  const [mode, setMode] = useState<'select' | 'erase'>('select');
  const [rightTab, setRightTab] = useState<RightTab>('grid-settings');

  const cols = cells[0]?.length || 0;
  const rows = cells.length;

  // Undo / redo history (structural changes only)
  const [past, setPast] = useState<Snapshot[]>([]);
  const [future, setFuture] = useState<Snapshot[]>([]);

  // Drag selection state
  const [dragStart, setDragStart] = useState<{r: number; c: number} | null>(null);
  const [dragEnd, setDragEnd] = useState<{r: number; c: number} | null>(null);
  const [isDragging, setIsDragging] = useState(false);

  // Naming popover state
  const [pendingRect, setPendingRect] = useState<{r1: number; c1: number; r2: number; c2: number} | null>(null);
  const [popoverPos, setPopoverPos] = useState<{x: number; y: number}>({ x: 0, y: 0 });
  const [areaNameInput, setAreaNameInput] = useState('');
  const [nameError, setNameError] = useState('');
  const nameInputRef = useRef<HTMLInputElement>(null);
  const lastMouseUp = useRef<{x: number; y: number}>({ x: 0, y: 0 });

  const dirty = () => setIsDirty(true);

  const snapshot = useCallback((): Snapshot => ({
    cells: cells.map(r => [...r]), colSizes: [...colSizes], rowSizes: [...rowSizes],
    positions: positions.map(p => ({ ...p })),
  }), [cells, colSizes, rowSizes, positions]);

  // Push current state to history before a structural mutation
  const pushHistory = useCallback(() => {
    setPast(p => [...p.slice(-49), snapshot()]);
    setFuture([]);
  }, [snapshot]);

  const restore = (s: Snapshot) => {
    setCells(s.cells); setColSizes(s.colSizes); setRowSizes(s.rowSizes); setPositions(s.positions);
    dirty();
  };
  const undo = () => {
    if (!past.length) return;
    const prev = past[past.length - 1];
    setPast(past.slice(0, -1));
    setFuture(f => [...f, snapshot()]);
    restore(prev);
  };
  const redo = () => {
    if (!future.length) return;
    const next = future[future.length - 1];
    setFuture(future.slice(0, -1));
    setPast(p => [...p, snapshot()]);
    restore(next);
  };

  const { data: gridData, isLoading } = useQuery({
    queryKey: ['grid', siteId, gridId],
    queryFn: () => grids.get(siteId, gridId).then((r: any) => r.data.data),
  });

  useEffect(() => {
    if (gridData) {
      setName(gridData.name);
      setGapX(gridData.gap_x);
      setGapY(gridData.gap_y);
      setContainerWidth(gridData.container_width);
      setContainerPadding(gridData.container_padding || '0 24px');
      setMinHeight(gridData.min_height || '');
      setAlignItems(gridData.align_items || 'stretch');
      setJustifyItems(gridData.justify_items || 'stretch');
      setOverflowX(gridData.overflow_x || 'visible');
      setLayoutMode(gridData.layout_mode || 'default');
      setBgJson(gridData.background_json || {});
      setFullBleed(gridData.full_bleed || false);
      setBreakpointsJson(gridData.breakpoints_json || {});
      setPositions(gridData.positions || []);
      const areaRows = ((gridData.areas || '').match(/"([^"]+)"/g) || []).map((r: string) => r.replace(/"/g, '').split(/\s+/));
      if (areaRows.length > 0 && areaRows[0].length > 0) {
        setCells(areaRows);
        setColSizes(gridData.col_tracks.split(/\s+/));
        setRowSizes(gridData.row_tracks.split(/\s+/));
      } else {
        setCells(Array.from({ length: 5 }, () => Array(4).fill('.')));
        setColSizes(Array(4).fill('1fr'));
        setRowSizes(Array(5).fill('auto'));
      }
      setPast([]); setFuture([]);
    }
  }, [gridData]);

  // Warn before leaving with unsaved changes
  useEffect(() => {
    const h = (e: BeforeUnloadEvent) => { if (isDirty) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', h);
    return () => window.removeEventListener('beforeunload', h);
  }, [isDirty]);

  // Derived: area info map (bounding rect + rectangularity check)
  const areaInfos = useMemo((): AreaInfo[] => {
    const map = new Map<string, AreaInfo>();
    cells.forEach((row, r) => row.forEach((cell, c) => {
      if (cell === '.') return;
      const a = map.get(cell);
      if (!a) map.set(cell, { name: cell, r1: r, c1: c, r2: r, c2: c, count: 1, valid: true });
      else {
        a.r1 = Math.min(a.r1, r); a.c1 = Math.min(a.c1, c);
        a.r2 = Math.max(a.r2, r); a.c2 = Math.max(a.c2, c);
        a.count++;
      }
    }));
    map.forEach(a => { a.valid = a.count === (a.r2 - a.r1 + 1) * (a.c2 - a.c1 + 1); });
    return Array.from(map.values());
  }, [cells]);
  const areaNames = useMemo(() => areaInfos.map(a => a.name), [areaInfos]);
  const invalidAreas = useMemo(() => new Set(areaInfos.filter(a => !a.valid).map(a => a.name)), [areaInfos]);

  const buildAreasString = useCallback(() =>
    cells.map(row => `"${row.join(' ')}"`).join(' '),
  [cells]);

  // Selection rectangle
  const getSelectionRect = () => {
    if (!dragStart || !dragEnd) return null;
    return {
      r1: Math.min(dragStart.r, dragEnd.r), c1: Math.min(dragStart.c, dragEnd.c),
      r2: Math.max(dragStart.r, dragEnd.r), c2: Math.max(dragStart.c, dragEnd.c),
    };
  };

  // Finish drag → erase immediately, or open naming popover
  const finishDrag = () => {
    const rect = getSelectionRect();
    setDragStart(null); setDragEnd(null); setIsDragging(false);
    if (!rect) return;

    if (mode === 'erase') {
      pushHistory();
      const n = cells.map(row => [...row]);
      for (let r = rect.r1; r <= rect.r2; r++)
        for (let c = rect.c1; c <= rect.c2; c++) n[r][c] = '.';
      setCells(n); dirty();
      return;
    }

    // Single click on an existing area = just select it, don't open the popover
    const single = rect.r1 === rect.r2 && rect.c1 === rect.c2;
    if (single && cells[rect.r1][rect.c1] !== '.') return;

    setPendingRect(rect);
    setAreaNameInput('');
    setNameError('');
    setPopoverPos({
      x: Math.min(lastMouseUp.current.x, window.innerWidth - 300),
      y: Math.min(lastMouseUp.current.y + 8, window.innerHeight - 300),
    });
    setTimeout(() => nameInputRef.current?.focus(), 30);
  };

  const cancelPending = () => { setPendingRect(null); setAreaNameInput(''); setNameError(''); };

  const confirmAreaName = (raw: string) => {
    const areaName = raw.trim().toLowerCase();
    if (!pendingRect) return;
    if (!/^[a-z][a-z0-9-]*$/.test(areaName)) {
      setNameError('Само малки латински букви, цифри и тире. Започва с буква.');
      return;
    }
    pushHistory();
    const n = cells.map(row => [...row]);
    for (let r = pendingRect.r1; r <= pendingRect.r2; r++)
      for (let c = pendingRect.c1; c <= pendingRect.c2; c++) n[r][c] = areaName;
    setCells(n);

    if (!positions.find(p => p.area_name === areaName)) {
      setPositions(prev => [...prev, {
        area_name: areaName,
        label: areaName.replace(/-/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase()),
        type: NAME_TYPE_MAP[areaName] || 'canvas',
        config_json: {}, scope: 'page',
        is_overridable: false, mobile_order: prev.length + 1,
      }]);
    }

    setSelectedArea(areaName);
    setRightTab('area-config');
    dirty();
    cancelPending();
  };

  // Grid structure mutations
  const addCol = () => { pushHistory(); setCells(p => p.map(r => [...r, '.'])); setColSizes(p => [...p, '1fr']); dirty(); };
  const rmCol = () => { if (cols <= 1) return; pushHistory(); setCells(p => p.map(r => r.slice(0, -1))); setColSizes(p => p.slice(0, -1)); dirty(); };
  const addRow = () => { pushHistory(); setCells(p => [...p, Array(cols).fill('.')]); setRowSizes(p => [...p, 'auto']); dirty(); };
  const rmRow = () => { if (rows <= 1) return; pushHistory(); setCells(p => p.slice(0, -1)); setRowSizes(p => p.slice(0, -1)); dirty(); };

  const deleteArea = (area: string) => {
    pushHistory();
    setCells(prev => prev.map(row => row.map(c => c === area ? '.' : c)));
    setPositions(prev => prev.filter(p => p.area_name !== area));
    if (selectedArea === area) { setSelectedArea(null); setRightTab('grid-settings'); }
    dirty();
  };

  const updatePosition = (area: string, updates: Partial<Position>) => {
    setPositions(p => p.map(pos => pos.area_name === area ? { ...pos, ...updates } : pos));
    dirty();
  };

  const applyColPreset = (preset: typeof COL_PRESETS[0]) => {
    pushHistory();
    const newCols = preset.cols.length;
    setColSizes(preset.cols);
    setCells(prev => {
      const newRows = Math.max(prev.length, 1);
      return Array.from({ length: newRows }, (_, ri) => {
        const row = prev[ri] || [];
        if (newCols > row.length) return [...row, ...Array(newCols - row.length).fill('.')];
        return row.slice(0, newCols);
      });
    });
    dirty();
  };

  const applyTemplate = (t: LayoutTemplate) => {
    pushHistory();
    setCells(t.cells.map(r => [...r]));
    setColSizes([...t.colSizes]);
    setRowSizes([...t.rowSizes]);
    const names = Array.from(new Set(t.cells.flat().filter(c => c !== '.')));
    setPositions(prev => names.map((n, i) => {
      const existing = prev.find(p => p.area_name === n);
      if (existing) return existing;
      return {
        area_name: n,
        label: n.replace(/-/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase()),
        type: t.types[n] || 'canvas',
        config_json: {}, scope: 'page',
        is_overridable: false, mobile_order: i + 1,
      };
    }));
    setSelectedArea(null);
    setRightTab('grid-settings');
    dirty();
  };

  // Move an area up/down in the mobile stacking order, then renumber 1..n
  const moveMobileOrder = (area: string, dir: -1 | 1) => {
    const active = positions.filter(p => areaNames.includes(p.area_name))
      .sort((a, b) => a.mobile_order - b.mobile_order);
    const idx = active.findIndex(p => p.area_name === area);
    const swap = idx + dir;
    if (idx < 0 || swap < 0 || swap >= active.length) return;
    [active[idx], active[swap]] = [active[swap], active[idx]];
    const orderMap = new Map(active.map((p, i) => [p.area_name, i + 1]));
    setPositions(prev => prev.map(p => orderMap.has(p.area_name) ? { ...p, mobile_order: orderMap.get(p.area_name)! } : p));
    dirty();
  };

  const [publishStatus, setPublishStatus] = useState<'idle' | 'publishing' | 'done' | 'error'>('idle');

  const saveMutation = useMutation({
    mutationFn: async () => {
      const active = positions.filter(p => areaNames.includes(p.area_name));
      await grids.update(siteId, gridId, {
        name, col_tracks: colSizes.join(' '), row_tracks: rowSizes.join(' '),
        areas: buildAreasString(), gap_x: gapX, gap_y: gapY,
        container_width: containerWidth, container_padding: containerPadding,
        min_height: minHeight || null, align_items: alignItems, justify_items: justifyItems,
        overflow_x: overflowX, layout_mode: layoutMode,
        background_json: Object.keys(bgJson).length ? bgJson : null,
        full_bleed: fullBleed,
        breakpoints_json: Object.keys(breakpointsJson).length ? breakpointsJson : null,
      });
      await grids.syncPositions(siteId, gridId, active);
    },
    onSuccess: async () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['grid', siteId, gridId] });
      setPublishStatus('publishing');
      try {
        await publishing.publish(siteId);
        setPublishStatus('done');
        setTimeout(() => setPublishStatus('idle'), 3000);
      } catch {
        setPublishStatus('error');
        setTimeout(() => setPublishStatus('idle'), 4000);
      }
    },
  });

  // Keyboard shortcuts
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
      if (e.key === 'Escape') {
        if (pendingRect) { cancelPending(); return; }
        if (!typing) { setSelectedArea(null); setRightTab('grid-settings'); }
        return;
      }
      if (typing) return;
      const mod = e.ctrlKey || e.metaKey;
      if (mod && e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      else if ((mod && e.key.toLowerCase() === 'z' && e.shiftKey) || (mod && e.key.toLowerCase() === 'y')) { e.preventDefault(); redo(); }
      else if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); if (isDirty && !saveMutation.isPending) saveMutation.mutate(); }
      else if ((e.key === 'Delete' || e.key === 'Backspace') && selectedArea) { e.preventDefault(); deleteArea(selectedArea); }
      else if (e.key.toLowerCase() === 'v') setMode('select');
      else if (e.key.toLowerCase() === 'e') setMode('erase');
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  });

  const sel = selectedArea ? positions.find(p => p.area_name === selectedArea) : null;

  // Shared grid template for all canvas layers
  const rowHeights = rowSizes.map(displayRowHeight);
  const gridStyle: React.CSSProperties = {
    display: 'grid',
    gridTemplateColumns: colSizes.map(displayCol).join(' '),
    gridTemplateRows: rowHeights.map(hpx => `${hpx}px`).join(' '),
    gap: '6px',
  };
  const selRect = pendingRect || (isDragging ? getSelectionRect() : null);

  if (isLoading) return <div className="flex items-center justify-center h-screen bg-base-200"><Loader2 className="h-8 w-8 animate-spin text-base-content/30" /></div>;

  return (
    <div className="flex flex-col h-screen bg-base-200 text-base-content select-none">
      {/* ─── Toolbar ─── */}
      <div className="flex items-center justify-between px-4 py-2 bg-base-100 border-b border-base-300 shrink-0">
        <div className="flex items-center gap-3 min-w-0">
          <button onClick={() => navigate(`/sites/${siteId}/grids`)} className="btn btn-ghost btn-sm btn-square"><ArrowLeft size={18} /></button>
          <input value={name} onChange={e => { setName(e.target.value); dirty(); }}
            className="text-base font-semibold bg-transparent border-none outline-none focus:bg-base-200/60 rounded-lg px-2 py-1 min-w-0 max-w-xs" />
          {isDirty && <span className="flex items-center gap-1.5 text-[11px] text-warning font-medium shrink-0"><span className="w-1.5 h-1.5 rounded-full bg-warning" /> Незапазено</span>}
        </div>
        <div className="flex items-center gap-2">
          <div className="flex items-center gap-0.5 mr-1">
            <button onClick={undo} disabled={!past.length} title="Undo (Ctrl+Z)"
              className="btn btn-ghost btn-sm btn-square disabled:opacity-20"><Undo2 size={15} /></button>
            <button onClick={redo} disabled={!future.length} title="Redo (Ctrl+Shift+Z)"
              className="btn btn-ghost btn-sm btn-square disabled:opacity-20"><Redo2 size={15} /></button>
          </div>
          {publishStatus === 'publishing' && <span className="text-xs text-warning flex items-center gap-1"><Loader2 size={12} className="animate-spin" /> Публикуване...</span>}
          {publishStatus === 'done' && <span className="text-xs text-success flex items-center gap-1"><Check size={12} /> Публикувано</span>}
          {publishStatus === 'error' && <span className="text-xs text-error">Publish грешка</span>}
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !isDirty}
            className="btn btn-primary btn-sm gap-1.5">
            {saveMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />} Запази & Публикувай
          </button>
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* ═══ LEFT: Grid canvas ═══ */}
        <div className="flex-1 p-5 overflow-auto">
          {/* Controls */}
          <div className="flex flex-wrap items-center gap-2 mb-4">
            <div className="flex items-center rounded-lg border border-base-300 bg-base-100 p-0.5">
              <button onClick={() => setMode('select')} title="Чертай зони (V)"
                className={`flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md transition-colors ${mode === 'select' ? 'bg-primary/15 text-primary font-medium' : 'text-base-content/50 hover:text-base-content'}`}>
                <MousePointer size={13} /> Чертай
              </button>
              <button onClick={() => setMode('erase')} title="Изтривай клетки (E)"
                className={`flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md transition-colors ${mode === 'erase' ? 'bg-error/15 text-error font-medium' : 'text-base-content/50 hover:text-base-content'}`}>
                <Eraser size={13} /> Изтрий
              </button>
            </div>

            <div className="h-5 w-px bg-base-300" />

            <div className="flex items-center gap-1 bg-base-100 rounded-lg border border-base-300 px-2 py-1.5 text-xs">
              <Columns size={12} className="text-base-content/40" />
              <button onClick={rmCol} className="p-0.5 hover:bg-base-200 rounded"><Minus size={12} /></button>
              <span className="w-6 text-center font-semibold tabular-nums">{cols}</span>
              <button onClick={addCol} className="p-0.5 hover:bg-base-200 rounded"><Plus size={12} /></button>
            </div>
            <div className="flex items-center gap-1 bg-base-100 rounded-lg border border-base-300 px-2 py-1.5 text-xs">
              <span className="text-base-content/40 text-[10px] font-medium">ред</span>
              <button onClick={rmRow} className="p-0.5 hover:bg-base-200 rounded"><Minus size={12} /></button>
              <span className="w-6 text-center font-semibold tabular-nums">{rows}</span>
              <button onClick={addRow} className="p-0.5 hover:bg-base-200 rounded"><Plus size={12} /></button>
            </div>

            <div className="h-5 w-px bg-base-300" />

            {/* Column presets dropdown */}
            <div className="dropdown">
              <div tabIndex={0} role="button" className="btn btn-sm btn-ghost border border-base-300 gap-1.5 text-xs font-normal">
                <Columns size={13} className="text-base-content/50" /> Колони <ChevronDown size={12} className="text-base-content/40" />
              </div>
              <ul tabIndex={0} className="dropdown-content z-40 menu p-1.5 mt-1 shadow-xl bg-base-100 border border-base-300 rounded-xl w-56">
                {COL_PRESETS.map(p => (
                  <li key={p.label}>
                    <button onClick={e => { applyColPreset(p); (e.currentTarget as HTMLElement).blur(); }} className="text-xs py-1.5">
                      {p.label}
                    </button>
                  </li>
                ))}
              </ul>
            </div>

            {/* Layout templates dropdown */}
            <div className="dropdown">
              <div tabIndex={0} role="button" className="btn btn-sm btn-ghost border border-base-300 gap-1.5 text-xs font-normal">
                <Sparkles size={13} className="text-accent" /> Шаблони <ChevronDown size={12} className="text-base-content/40" />
              </div>
              <div tabIndex={0} className="dropdown-content z-40 p-1.5 mt-1 shadow-xl bg-base-100 border border-base-300 rounded-xl w-80">
                <p className="px-2.5 pt-1.5 pb-1 text-[10px] text-base-content/40">Замества текущата подредба (Ctrl+Z връща)</p>
                {LAYOUT_TEMPLATES.map(t => (
                  <button key={t.id} onClick={e => { applyTemplate(t); (e.currentTarget as HTMLElement).blur(); }}
                    className="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-base-200 text-left transition-colors">
                    <LayoutThumb cells={t.cells} types={t.types} />
                    <div className="min-w-0">
                      <div className="text-xs font-medium">{t.label}</div>
                      <div className="text-[10px] text-base-content/40 truncate">{t.desc}</div>
                    </div>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Column size ruler */}
          <div className="mb-1.5 ml-[63px] mr-[9px]" style={{ ...gridStyle, gridTemplateRows: 'auto' }}>
            {colSizes.map((s, i) => (
              <input key={i} value={s} onChange={e => { const n = [...colSizes]; n[i] = e.target.value; setColSizes(n); dirty(); }}
                title="CSS стойност: 1fr, 2fr, 300px, 25%, minmax(200px,1fr), auto"
                className="text-center text-[10px] font-mono text-base-content/50 bg-base-100 rounded-md px-0.5 py-1 border border-base-300 min-w-0 focus:outline-none focus:border-primary focus:text-base-content transition-colors" />
            ))}
          </div>

          {/* Grid + row ruler */}
          <div className="flex">
            <div className="flex flex-col mr-1.5 shrink-0" style={{ gap: '6px', paddingTop: '9px' }}>
              {rowSizes.map((s, i) => (
                <div key={i} className="flex items-center" style={{ height: rowHeights[i] }}>
                  <input value={s} onChange={e => { const n = [...rowSizes]; n[i] = e.target.value; setRowSizes(n); dirty(); }}
                    title="CSS стойност: auto, 1fr, 100px, 50vh, minmax(80px,auto)"
                    className="text-[10px] font-mono text-base-content/50 bg-base-100 rounded-md px-0.5 py-1 border border-base-300 w-12 text-center focus:outline-none focus:border-primary focus:text-base-content transition-colors" />
                </div>
              ))}
            </div>

            {/* THE CANVAS: interaction cells + area overlay + selection overlay */}
            <div className={`relative flex-1 rounded-xl border p-2 transition-colors ${mode === 'erase' ? 'border-error/40 bg-error/[0.03]' : 'border-base-300 bg-base-100'}`}
              onMouseLeave={() => { if (isDragging) finishDrag(); }}
              onMouseUp={e => { lastMouseUp.current = { x: e.clientX, y: e.clientY }; if (isDragging) finishDrag(); }}>

              {/* Layer 1: interactive cells */}
              <div style={gridStyle}>
                {cells.flatMap((row, ri) => row.map((cell, ci) => {
                  const active = cell !== '.';
                  return (
                    <div key={`${ri}-${ci}`}
                      className={`flex items-center justify-center rounded-lg transition-colors ${active ? '' : 'border border-dashed border-base-content/10 hover:border-base-content/25 hover:bg-base-content/[0.03]'}`}
                      style={{ cursor: mode === 'erase' ? 'cell' : active ? 'pointer' : 'crosshair' }}
                      onMouseDown={e => {
                        e.preventDefault();
                        if (pendingRect) { cancelPending(); return; }
                        setDragStart({ r: ri, c: ci }); setDragEnd({ r: ri, c: ci }); setIsDragging(true);
                        if (cell !== '.' && mode === 'select') { setSelectedArea(cell); setRightTab('area-config'); }
                      }}
                      onMouseEnter={() => { if (isDragging) setDragEnd({ r: ri, c: ci }); }}
                    >
                      {!active && <Plus size={12} className="text-base-content/10" />}
                    </div>
                  );
                }))}
              </div>

              {/* Layer 2: merged area blocks */}
              <div className="absolute inset-2 pointer-events-none" style={gridStyle}>
                {areaInfos.filter(a => a.valid).map(a => {
                  const pos = positions.find(p => p.area_name === a.name);
                  const color = TYPE_COLORS[pos?.type ?? ''];
                  const isSel = selectedArea === a.name;
                  return (
                    <div key={a.name}
                      className="rounded-lg flex flex-col items-center justify-center gap-0.5 transition-all"
                      style={{
                        gridColumn: `${a.c1 + 1} / ${a.c2 + 2}`,
                        gridRow: `${a.r1 + 1} / ${a.r2 + 2}`,
                        backgroundColor: color + (isSel ? '2e' : '1c'),
                        border: `1.5px solid ${color}${isSel ? '' : '66'}`,
                        boxShadow: isSel ? `0 0 0 2px ${color}55, 0 8px 24px -8px ${color}40` : 'none',
                      }}>
                      <div className="text-xs font-bold leading-tight" style={{ color }}>{pos?.label || a.name}</div>
                      <div className="text-[9px] leading-tight font-mono opacity-70" style={{ color }}>{a.name} · {pos?.type}</div>
                    </div>
                  );
                })}
                {/* Invalid (non-rectangular) areas: mark their cells */}
                {invalidAreas.size > 0 && cells.flatMap((row, ri) => row.map((cell, ci) =>
                  invalidAreas.has(cell) ? (
                    <div key={`inv-${ri}-${ci}`}
                      className="rounded-lg border-2 border-dashed border-error/70 bg-error/10 flex items-center justify-center"
                      style={{ gridColumn: ci + 1, gridRow: ri + 1 }}>
                      <span className="text-[9px] font-bold text-error">{cell}</span>
                    </div>
                  ) : null
                ))}
              </div>

              {/* Layer 3: live selection rect */}
              {selRect && (
                <div className="absolute inset-2 pointer-events-none" style={gridStyle}>
                  <div className={`rounded-lg border-2 ${mode === 'erase' ? 'border-error bg-error/15' : 'border-primary bg-primary/15'} flex items-center justify-center`}
                    style={{
                      gridColumn: `${selRect.c1 + 1} / ${selRect.c2 + 2}`,
                      gridRow: `${selRect.r1 + 1} / ${selRect.r2 + 2}`,
                    }}>
                    {mode === 'erase'
                      ? <X size={16} className="text-error/60" />
                      : <span className="text-xs font-semibold text-primary/80">{pendingRect ? 'Дай име на зоната…' : 'Нова зона'}</span>}
                  </div>
                </div>
              )}
            </div>
          </div>

          {invalidAreas.size > 0 && (
            <div className="mt-3 flex items-start gap-2 px-3 py-2 rounded-lg bg-error/10 border border-error/30 text-xs text-error">
              <AlertTriangle size={14} className="mt-0.5 shrink-0" />
              <div>
                <strong>Невалидни зони: {Array.from(invalidAreas).join(', ')}.</strong>{' '}
                CSS Grid изисква всяка зона да е правоъгълник. Дочертай или изтрий маркираните клетки, иначе подредбата няма да работи на сайта.
              </div>
            </div>
          )}

          <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-base-content/35">
            <span><kbd className="kbd kbd-xs">V</kbd> чертай — влачи правоъгълник върху празни клетки</span>
            <span><kbd className="kbd kbd-xs">E</kbd> гумичка</span>
            <span><kbd className="kbd kbd-xs">Del</kbd> изтрива избраната зона</span>
            <span><kbd className="kbd kbd-xs">Ctrl</kbd>+<kbd className="kbd kbd-xs">Z</kbd> undo</span>
            <span><kbd className="kbd kbd-xs">Ctrl</kbd>+<kbd className="kbd kbd-xs">S</kbd> запази</span>
          </div>
        </div>

        {/* ═══ RIGHT: Config panel ═══ */}
        <div className="w-96 bg-base-100 border-l border-base-300 overflow-y-auto shrink-0">
          {/* Tab switcher */}
          <div className="flex border-b border-base-300 sticky top-0 bg-base-100 z-10">
            <button onClick={() => { setRightTab('grid-settings'); setSelectedArea(null); }}
              className={`flex-1 px-3 py-2.5 text-xs font-medium transition-colors ${rightTab === 'grid-settings' ? 'border-b-2 border-primary text-primary' : 'text-base-content/50 hover:text-base-content/80'}`}>
              <Settings size={12} className="inline mr-1" /> Настройки грид
            </button>
            <button onClick={() => setRightTab('area-config')}
              className={`flex-1 px-3 py-2.5 text-xs font-medium transition-colors ${rightTab === 'area-config' ? 'border-b-2 border-primary text-primary' : 'text-base-content/50 hover:text-base-content/80'}`}>
              <Columns size={12} className="inline mr-1" /> {sel ? sel.label : 'Зони'}
            </button>
          </div>

          {rightTab === 'grid-settings' && (
            <div className="p-3 space-y-3">
              {/* ── Container ── */}
              <Section title="Контейнер" icon={<Monitor size={13} className="text-base-content/40" />} defaultOpen={true}
                hint="Контейнерът определя максималната ширина на цялата страница. Съдържанието се центрира в него.">
                <Field label="Макс. ширина (max-width)" hint="Колко широк може да бъде сайтът. Примери: 1200px, 1400px, 100% (пълен екран), 90vw">
                  <Input value={containerWidth} onChange={v => { setContainerWidth(v); dirty(); }} placeholder="1200px" />
                </Field>
                <Field label="Вътрешен отстъп (padding)" hint="Разстояние от ръба на контейнера до съдържанието. Пример: 0 24px (горе/долу ляво/дясно)">
                  <Input value={containerPadding} onChange={v => { setContainerPadding(v); dirty(); }} placeholder="0 24px" />
                </Field>
                <Field label="Мин. височина" hint="Минимална височина на грида. Примери: 100vh (цял екран), 500px, auto">
                  <Input value={minHeight} onChange={v => { setMinHeight(v); dirty(); }} placeholder="auto" />
                </Field>
                <label className="flex items-center gap-2 p-2.5 bg-primary/10 border border-primary/20 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={fullBleed} onChange={e => { setFullBleed(e.target.checked); dirty(); }}
                    className="checkbox checkbox-primary checkbox-xs" />
                  <div>
                    <p className="text-xs font-medium text-primary">Full-bleed обвивка</p>
                    <p className="text-[10px] text-base-content/50">Фонът на грида покрива целия екран, съдържанието остава центрирано</p>
                  </div>
                </label>
              </Section>

              {/* ── Gap ── */}
              <Section title="Разстояние (Gap)" defaultOpen={true}
                hint="Разстоянието между зоните в грида.">
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Хоризонтално (gap-x)">
                    <Input value={gapX} onChange={v => { setGapX(v); dirty(); }} placeholder="16px" />
                  </Field>
                  <Field label="Вертикално (gap-y)">
                    <Input value={gapY} onChange={v => { setGapY(v); dirty(); }} placeholder="12px" />
                  </Field>
                </div>
              </Section>

              {/* ── Alignment ── */}
              <Section title="Подравняване (Alignment)" icon={<LayoutGrid size={13} className="text-base-content/40" />}
                hint="Как се подравнява съдържанието вътре в зоните. Stretch = запълва цялата зона.">
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Вертикално (align-items)">
                    <Select value={alignItems} onChange={v => { setAlignItems(v); dirty(); }} options={ALIGN_OPTIONS} />
                  </Field>
                  <Field label="Хоризонтално (justify-items)">
                    <Select value={justifyItems} onChange={v => { setJustifyItems(v); dirty(); }} options={ALIGN_OPTIONS} />
                  </Field>
                </div>
              </Section>

              {/* ── Layout Mode ── */}
              <Section title="Режим на страницата" icon={<Eye size={13} className="text-base-content/40" />}
                hint="Определя как се показва страницата — нормално скролиране, хоризонтален слайдер, или snap секции на цял екран.">
                <div className="space-y-1.5">
                  {[
                    { value: 'default', label: 'Нормален', desc: 'Стандартно вертикално скролиране' },
                    { value: 'horizontal-scroll', label: 'Хоризонтален скрол', desc: 'Зоните се редят хоризонтално, всяка заема цял екран. Scroll snap за плавно превъртане.' },
                    { value: 'snap-sections', label: 'Snap секции (вертикални)', desc: 'Всяка зона е на цял екран. При скролиране щраква на следващата секция.' },
                  ].map(m => (
                    <button key={m.value} onClick={() => { setLayoutMode(m.value); dirty(); }}
                      className={`w-full text-left px-3 py-2.5 rounded-lg border transition-all ${layoutMode === m.value ? 'border-primary bg-primary/10' : 'border-base-300 hover:border-base-content/25'}`}>
                      <div className="text-xs font-medium">{m.label}</div>
                      <p className="text-[10px] text-base-content/40 mt-0.5">{m.desc}</p>
                    </button>
                  ))}
                </div>
              </Section>

              {/* ── Background ── */}
              <Section title="Фон на грида" icon={<Palette size={13} className="text-base-content/40" />}
                hint="Фон на целия грид контейнер. Работи добре с full-bleed.">
                <Field label="Цвят">
                  <div className="flex gap-2">
                    <input type="color" value={bgJson.color || '#ffffff'} onChange={e => { setBgJson({ ...bgJson, color: e.target.value }); dirty(); }}
                      className="w-8 h-8 rounded-lg border border-base-300 cursor-pointer bg-transparent" />
                    <Input value={bgJson.color || ''} onChange={v => { setBgJson({ ...bgJson, color: v }); dirty(); }} placeholder="#ffffff или transparent" />
                  </div>
                </Field>
                <Field label="Градиент (CSS)" hint="Пример: linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                  <Input value={bgJson.gradient || ''} onChange={v => { setBgJson({ ...bgJson, gradient: v }); dirty(); }} placeholder="linear-gradient(...)" />
                </Field>
                <Field label="Изображение URL" hint="URL на фоново изображение. Покрива цялата площ (cover).">
                  <Input value={bgJson.image || ''} onChange={v => { setBgJson({ ...bgJson, image: v }); dirty(); }} placeholder="https://..." />
                </Field>
                <Field label="Overlay" hint="Полупрозрачен слой върху фона. Пример: rgba(0,0,0,0.5) за тъмен overlay">
                  <Input value={bgJson.overlay || ''} onChange={v => { setBgJson({ ...bgJson, overlay: v }); dirty(); }} placeholder="rgba(0,0,0,0.4)" />
                </Field>
                {(bgJson.color || bgJson.gradient || bgJson.image) && (
                  <button onClick={() => { setBgJson({}); dirty(); }} className="text-xs text-error hover:underline">Изчисти фон</button>
                )}
              </Section>

              {/* ── Responsive Breakpoints ── */}
              <Section title="Responsive (Tablet / Mobile)" icon={<Smartphone size={13} className="text-base-content/40" />}
                hint="Различна подредба за таблет (≤1024px) и мобилен (≤768px). Ако не зададеш, колоните стават 1fr на мобилен.">
                <BreakpointEditor label="Таблет (≤1024px)" bp={breakpointsJson.tablet || {}}
                  onChange={v => { setBreakpointsJson({ ...breakpointsJson, tablet: v }); dirty(); }} />
                <div className="border-t border-base-300 my-2" />
                <BreakpointEditor label="Мобилен (≤768px)" bp={breakpointsJson.mobile || {}}
                  onChange={v => { setBreakpointsJson({ ...breakpointsJson, mobile: v }); dirty(); }} />
              </Section>

              {/* ── Mobile stacking order ── */}
              <Section title="Мобилна подредба" icon={<Smartphone size={13} className="text-base-content/40" />}
                hint="Редът, в който зоните се подреждат една под друга на мобилен екран.">
                {areaNames.length === 0 ? (
                  <p className="text-xs text-base-content/30 text-center py-3">Няма зони</p>
                ) : (
                  <div className="space-y-1">
                    {positions.filter(p => areaNames.includes(p.area_name))
                      .sort((a, b) => a.mobile_order - b.mobile_order)
                      .map((p, i, arr) => {
                        const color = TYPE_COLORS[p.type];
                        return (
                          <div key={p.area_name} className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-base-300 bg-base-200/40">
                            <span className="text-[10px] font-mono text-base-content/30 w-4">{i + 1}</span>
                            <span className="w-2.5 h-2.5 rounded-sm shrink-0" style={{ backgroundColor: color + '50', border: `1.5px solid ${color}` }} />
                            <span className="flex-1 text-xs font-medium truncate">{p.label}</span>
                            <button onClick={() => moveMobileOrder(p.area_name, -1)} disabled={i === 0}
                              className="p-1 rounded hover:bg-base-300 disabled:opacity-15"><ArrowUp size={12} /></button>
                            <button onClick={() => moveMobileOrder(p.area_name, 1)} disabled={i === arr.length - 1}
                              className="p-1 rounded hover:bg-base-300 disabled:opacity-15"><ArrowDown size={12} /></button>
                          </div>
                        );
                      })}
                  </div>
                )}
              </Section>

              {/* ── Areas list ── */}
              <Section title={`Зони (${areaNames.length})`} defaultOpen={true}>
                {areaNames.length === 0 ? (
                  <div className="text-center py-8 text-base-content/25">
                    <LayoutGrid className="h-8 w-8 mx-auto mb-2" />
                    <p className="text-xs">Начертай зони на грида,<br />или избери готов шаблон</p>
                  </div>
                ) : (
                  <div className="space-y-1">
                    {areaInfos.map(a => {
                      const p = positions.find(pos => pos.area_name === a.name);
                      const color = TYPE_COLORS[p?.type ?? ''];
                      return (
                        <button key={a.name} onClick={() => { setSelectedArea(a.name); setRightTab('area-config'); }}
                          className={`w-full flex items-center gap-2 px-3 py-2 rounded-lg border text-left transition-all ${selectedArea === a.name ? 'border-primary bg-primary/10' : 'border-base-300 hover:border-base-content/25'}`}>
                          <span className="w-3 h-3 rounded shrink-0" style={{ backgroundColor: color + '30', border: `2px solid ${color}` }} />
                          <div className="flex-1 min-w-0">
                            <div className="text-xs font-medium">{p?.label || a.name}</div>
                            <div className="text-[10px] text-base-content/40">{p?.type} · {a.count} клетки · {p?.scope}</div>
                          </div>
                          {!a.valid && <AlertTriangle size={13} className="text-error shrink-0" />}
                        </button>
                      );
                    })}
                  </div>
                )}
              </Section>
            </div>
          )}

          {rightTab === 'area-config' && sel && (
            <div className="p-3 space-y-3">
              {/* Area header */}
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="w-3.5 h-3.5 rounded" style={{ backgroundColor: TYPE_COLORS[sel.type] + '30', border: `2px solid ${TYPE_COLORS[sel.type]}` }} />
                  <div>
                    <h3 className="font-semibold text-base leading-tight">{sel.label}</h3>
                    <p className="text-[10px] text-base-content/40 font-mono">{selectedArea}</p>
                  </div>
                </div>
                <div className="flex gap-1">
                  <button onClick={() => deleteArea(selectedArea!)} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Изтрий зона (Del)"><Trash2 size={14} /></button>
                  <button onClick={() => { setSelectedArea(null); setRightTab('grid-settings'); }} className="btn btn-ghost btn-xs btn-square text-base-content/40" title="Затвори (Esc)"><X size={14} /></button>
                </div>
              </div>

              {/* ── Content Type ── */}
              <Section title="Тип съдържание" defaultOpen={true}
                hint="Определя какво се показва в тази зона — блоков редактор, меню, списък постове, и т.н.">
                <Field label="Име">
                  <Input value={sel.label} onChange={v => updatePosition(selectedArea!, { label: v })} />
                </Field>
                <div className="grid grid-cols-2 gap-1.5">
                  {(['canvas','menu','query','fixed','widget','static'] as PositionType[]).map(t => (
                    <button key={t} onClick={() => updatePosition(selectedArea!, { type: t })}
                      title={TYPE_LABELS[t]}
                      className={`text-left px-2.5 py-2 rounded-lg border transition-all ${sel.type === t ? 'bg-base-200/60' : 'border-base-300 hover:border-base-content/25'}`}
                      style={sel.type === t ? { borderColor: TYPE_COLORS[t] } : undefined}>
                      <div className="flex items-center gap-1.5">
                        <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: TYPE_COLORS[t] }} />
                        <span className="text-xs font-medium capitalize">{t}</span>
                      </div>
                      <p className="text-[9px] text-base-content/40 mt-0.5 leading-tight">{TYPE_LABELS[t]}</p>
                    </button>
                  ))}
                </div>
              </Section>

              {/* ── Scope & Options ── */}
              <Section title="Обхват и опции" defaultOpen={true}
                hint="Scope определя дали зоната е уникална за всяка страница, или споделена.">
                <Field label="Scope" hint="page = уникално за всяка страница, site = еднакво навсякъде, grid = за всички ползващи този грид">
                  <Select value={sel.scope} onChange={v => updatePosition(selectedArea!, { scope: v })} options={[
                    { value: 'page', label: 'Per page (уникално)' },
                    { value: 'site', label: 'Site-wide (навсякъде)' },
                    { value: 'grid', label: 'Per grid (споделено)' },
                  ]} />
                </Field>
                <label className="flex items-center gap-2 p-2.5 bg-base-200/50 border border-base-300 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={sel.is_overridable} onChange={e => updatePosition(selectedArea!, { is_overridable: e.target.checked })}
                    className="checkbox checkbox-primary checkbox-xs" />
                  <div>
                    <p className="text-xs font-medium">Overridable</p>
                    <p className="text-[10px] text-base-content/40">Позволява на отделни страници да заменят съдържанието</p>
                  </div>
                </label>
              </Section>

              {/* ── Size & Layout ── */}
              <Section title="Размери и подравняване"
                hint="Контролира минимална/максимална височина/ширина и как се подравнява съдържанието вътре в зоната.">
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Мин. височина" hint="100vh, 400px, auto">
                    <Input value={sel.min_height || ''} onChange={v => updatePosition(selectedArea!, { min_height: v || undefined })} placeholder="auto" />
                  </Field>
                  <Field label="Макс. ширина" hint="800px, 60%, none">
                    <Input value={sel.max_width || ''} onChange={v => updatePosition(selectedArea!, { max_width: v || undefined })} placeholder="none" />
                  </Field>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Верт. подравняване">
                    <Select value={sel.align_self || ''} onChange={v => updatePosition(selectedArea!, { align_self: v || undefined })}
                      options={[{ value: '', label: '(наследи)' }, ...ALIGN_OPTIONS]} />
                  </Field>
                  <Field label="Хориз. подравняване">
                    <Select value={sel.justify_self || ''} onChange={v => updatePosition(selectedArea!, { justify_self: v || undefined })}
                      options={[{ value: '', label: '(наследи)' }, ...ALIGN_OPTIONS]} />
                  </Field>
                </div>
                <Field label="Overflow" hint="Какво се случва ако съдържанието е по-голямо от зоната">
                  <Select value={sel.overflow || ''} onChange={v => updatePosition(selectedArea!, { overflow: v || undefined })}
                    options={[
                      { value: '', label: 'Видимо (visible)' },
                      { value: 'hidden', label: 'Скрито (hidden)' },
                      { value: 'auto', label: 'Скрол при нужда (auto)' },
                      { value: 'scroll', label: 'Винаги скрол (scroll)' },
                    ]} />
                </Field>
                <label className="flex items-center gap-2 p-2.5 bg-primary/10 border border-primary/20 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={sel.full_bleed || false} onChange={e => updatePosition(selectedArea!, { full_bleed: e.target.checked })}
                    className="checkbox checkbox-primary checkbox-xs" />
                  <div>
                    <p className="text-xs font-medium text-primary">Full-bleed зона</p>
                    <p className="text-[10px] text-base-content/50">Зоната излиза извън контейнера и покрива целия екран по ширина</p>
                  </div>
                </label>
              </Section>

              {/* ── Padding ── */}
              <Section title="Padding (вътрешен отстъп)"
                hint="Разстоянието вътре в зоната между ръба и съдържанието. Можеш да ползваш px, %, rem.">
                <PaddingEditor value={sel.padding_json || {}} onChange={v => updatePosition(selectedArea!, { padding_json: v })} />
              </Section>

              {/* ── Background ── */}
              <Section title="Фон на зоната" icon={<Palette size={13} className="text-base-content/40" />}
                hint="Цвят, градиент или изображение зад съдържанието на тази зона.">
                <Field label="Цвят">
                  <div className="flex gap-2">
                    <input type="color" value={sel.background_json?.color || '#ffffff'} onChange={e => updatePosition(selectedArea!, { background_json: { ...sel.background_json, color: e.target.value } })}
                      className="w-8 h-8 rounded-lg border border-base-300 cursor-pointer bg-transparent" />
                    <Input value={sel.background_json?.color || ''} onChange={v => updatePosition(selectedArea!, { background_json: { ...sel.background_json, color: v } })} placeholder="#ffffff" />
                  </div>
                </Field>
                <Field label="Градиент" hint="Пример: linear-gradient(to bottom, #e0e7ff, #ffffff)">
                  <Input value={sel.background_json?.gradient || ''} onChange={v => updatePosition(selectedArea!, { background_json: { ...sel.background_json, gradient: v } })} placeholder="linear-gradient(...)" />
                </Field>
                <Field label="Изображение URL">
                  <Input value={sel.background_json?.image || ''} onChange={v => updatePosition(selectedArea!, { background_json: { ...sel.background_json, image: v } })} placeholder="https://..." />
                </Field>
                <Field label="Overlay (полупрозрачен слой)" hint="Показва се ВЪРХУ фона, ЗАД съдържанието. rgba(0,0,0,0.5) = тъмен полупрозрачен.">
                  <Input value={sel.background_json?.overlay || ''} onChange={v => updatePosition(selectedArea!, { background_json: { ...sel.background_json, overlay: v } })} placeholder="rgba(0,0,0,0.4)" />
                </Field>
              </Section>

              {/* ── Border & Shadow ── */}
              <Section title="Рамка и сянка"
                hint="Рамка около зоната и сянка за визуална дълбочина.">
                <div className="grid grid-cols-3 gap-2">
                  <Field label="Дебелина">
                    <Input value={sel.border_json?.width || ''} onChange={v => updatePosition(selectedArea!, { border_json: { ...sel.border_json, width: v } })} placeholder="1px" />
                  </Field>
                  <Field label="Цвят">
                    <Input value={sel.border_json?.color || ''} onChange={v => updatePosition(selectedArea!, { border_json: { ...sel.border_json, color: v } })} placeholder="#e5e7eb" />
                  </Field>
                  <Field label="Стил">
                    <Select value={sel.border_json?.style || 'solid'} onChange={v => updatePosition(selectedArea!, { border_json: { ...sel.border_json, style: v } })}
                      options={[{ value: 'solid', label: 'Плътна' }, { value: 'dashed', label: 'Пунктир' }, { value: 'dotted', label: 'Точки' }, { value: 'none', label: 'Без' }]} />
                  </Field>
                </div>
                <Field label="Заобляне (border-radius)" hint="Примери: 8px, 16px, 50% (кръг), 0">
                  <Input value={sel.border_json?.radius || ''} onChange={v => updatePosition(selectedArea!, { border_json: { ...sel.border_json, radius: v } })} placeholder="0" />
                </Field>
                <Field label="Сянка (box-shadow)">
                  <div className="flex flex-wrap gap-1 mb-1">
                    {SHADOW_PRESETS.map(s => (
                      <button key={s.label} onClick={() => updatePosition(selectedArea!, { shadow: s.value || undefined })}
                        className={`px-2 py-0.5 text-[10px] rounded-md border transition-colors ${sel.shadow === s.value || (!sel.shadow && !s.value) ? 'border-primary text-primary bg-primary/10' : 'border-base-300 text-base-content/50 hover:border-base-content/30'}`}>
                        {s.label}
                      </button>
                    ))}
                  </div>
                  <Input value={sel.shadow || ''} onChange={v => updatePosition(selectedArea!, { shadow: v || undefined })} placeholder="0 4px 6px rgba(0,0,0,0.1)" />
                </Field>
              </Section>

              {/* ── CSS Class ── */}
              <Section title="Допълнителни настройки">
                <Field label="CSS клас" hint="Допълнителен CSS клас за тази зона. За custom стилове.">
                  <Input value={sel.css_class || ''} onChange={v => updatePosition(selectedArea!, { css_class: v || undefined })} placeholder="my-custom-class" />
                </Field>
              </Section>

              {/* ── Type-specific config ── */}
              {sel.type === 'menu' && (
                <Section title="Настройки на менюто" defaultOpen={true}>
                  <Field label="Локация на менюто">
                    <Select value={(sel.config_json as any).location || 'header'} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, location: v } })}
                      options={[{ value: 'header', label: 'Header (основно)' }, { value: 'footer', label: 'Footer' }, { value: 'sidebar', label: 'Sidebar' }]} />
                  </Field>
                </Section>
              )}

              {sel.type === 'query' && (
                <Section title="Настройки на заявката" defaultOpen={true}>
                  <Field label="Брой постове">
                    <Input value={String((sel.config_json as any).count || 6)} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, count: parseInt(v) || 6 } })} />
                  </Field>
                  <Field label="Оформление">
                    <Select value={(sel.config_json as any).layout || 'grid'} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, layout: v } })}
                      options={[
                        { value: 'grid', label: 'Мрежа (grid cards)' },
                        { value: 'list', label: 'Списък' },
                        { value: 'featured', label: 'Featured (първият голям)' },
                      ]} />
                  </Field>
                  <Field label="Стил на картата">
                    <Select value={(sel.config_json as any).card_style || 'default'} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, card_style: v } })}
                      options={[
                        { value: 'default', label: 'По подразбиране' },
                        { value: 'compact', label: 'Компактен' },
                        { value: 'horizontal', label: 'Хоризонтален' },
                        { value: 'overlay', label: 'Overlay (снимка фон)' },
                      ]} />
                  </Field>
                </Section>
              )}

              {sel.type === 'static' && (
                <Section title="Авто-съдържание" defaultOpen={true}>
                  <Field label="Какво да покаже">
                    <Select value={(sel.config_json as any).partial || ''} onChange={v => updatePosition(selectedArea!, { config_json: { partial: v } })}
                      options={[{ value: '', label: 'Избери...' }, { value: 'breadcrumb', label: 'Breadcrumb' }, { value: 'pagination', label: 'Пагинация' }]} />
                  </Field>
                </Section>
              )}

              {sel.type === 'widget' && (
                <Section title="Уиджети" defaultOpen={true}>
                  <WidgetConfigurator
                    widgets={(sel.config_json as any).widgets || []}
                    sticky={(sel.config_json as any).sticky || false}
                    onChange={(widgets, sticky) => updatePosition(selectedArea!, { config_json: { ...sel.config_json, widgets, sticky } })}
                  />
                </Section>
              )}

              {sel.type === 'fixed' && (
                <Section title="Фиксирано съдържание" defaultOpen={true}>
                  <div className="text-xs text-base-content/60 space-y-1">
                    <p>Тази зона показва едно и също съдържание на всяка страница.</p>
                    <p>Можеш да зададеш Blade partial:</p>
                    <Input value={(sel.config_json as any).blade_partial || ''} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, blade_partial: v } })}
                      placeholder="напр. header, footer" />
                  </div>
                </Section>
              )}
            </div>
          )}

          {rightTab === 'area-config' && !sel && (
            <div className="p-4 text-center text-base-content/30 py-20">
              <LayoutGrid className="h-10 w-10 mx-auto mb-3 opacity-50" />
              <p className="text-sm">Кликни на зона от грида</p>
              <p className="text-xs mt-1">или начертай нова зона с мишката</p>
            </div>
          )}
        </div>
      </div>

      {/* ─── Area naming popover ─── */}
      {pendingRect && (
        <>
          <div className="fixed inset-0 z-40" onMouseDown={cancelPending} />
          <div className="fixed z-50 w-72 bg-base-100 border border-base-300 rounded-xl shadow-2xl p-3.5"
            style={{ left: popoverPos.x, top: popoverPos.y }}
            onMouseDown={e => e.stopPropagation()}>
            <p className="text-xs font-semibold mb-2">Нова зона</p>
            <form onSubmit={e => { e.preventDefault(); confirmAreaName(areaNameInput); }}>
              <input ref={nameInputRef} value={areaNameInput}
                onChange={e => { setAreaNameInput(e.target.value); setNameError(''); }}
                placeholder="име, напр. hero"
                className={`${INPUT_CLS} font-mono ${nameError ? 'border-error' : ''}`} />
            </form>
            {nameError && <p className="text-[10px] text-error mt-1">{nameError}</p>}
            <div className="flex flex-wrap gap-1 mt-2.5">
              {NAME_SUGGESTIONS.filter(s => !areaNames.includes(s)).slice(0, 8).map(s => (
                <button key={s} onClick={() => confirmAreaName(s)} className={`${CHIP_CLS} font-mono`}>
                  {s}
                </button>
              ))}
            </div>
            <div className="flex justify-end gap-1.5 mt-3">
              <button onClick={cancelPending} className="btn btn-ghost btn-xs">Отказ</button>
              <button onClick={() => confirmAreaName(areaNameInput)} disabled={!areaNameInput.trim()}
                className="btn btn-primary btn-xs gap-1"><Check size={11} /> Създай</button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════
// PADDING EDITOR
// ═══════════════════════════════════════════
function PaddingEditor({ value, onChange }: { value: Record<string, string>; onChange: (v: Record<string, string>) => void }) {
  const update = (side: string, v: string) => onChange({ ...value, [side]: v });
  const PRESETS = [
    { label: 'Без', v: { top: '0', right: '0', bottom: '0', left: '0' } },
    { label: 'S', v: { top: '8px', right: '16px', bottom: '8px', left: '16px' } },
    { label: 'M', v: { top: '16px', right: '24px', bottom: '16px', left: '24px' } },
    { label: 'L', v: { top: '32px', right: '40px', bottom: '32px', left: '40px' } },
    { label: 'XL', v: { top: '48px', right: '64px', bottom: '48px', left: '64px' } },
    { label: '2XL', v: { top: '80px', right: '80px', bottom: '80px', left: '80px' } },
  ];
  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-1">
        {PRESETS.map(p => (
          <button key={p.label} onClick={() => onChange(p.v)} className={CHIP_CLS}>
            {p.label}
          </button>
        ))}
      </div>
      <div className="grid grid-cols-4 gap-1">
        {(['top', 'right', 'bottom', 'left'] as const).map(s => (
          <div key={s}>
            <label className="block text-[10px] text-base-content/40 text-center mb-0.5">{s}</label>
            <input value={value[s] || '0'} onChange={e => update(s, e.target.value)}
              className="w-full text-center text-[11px] bg-base-200/60 border border-base-300 rounded-md px-1 py-1 focus:outline-none focus:border-primary" placeholder="0" />
          </div>
        ))}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════
// BREAKPOINT EDITOR
// ═══════════════════════════════════════════
function BreakpointEditor({ label, bp, onChange }: {
  label: string; bp: Record<string, string>; onChange: (v: Record<string, string>) => void;
}) {
  const update = (k: string, v: string) => {
    const next = { ...bp, [k]: v };
    if (!v) delete next[k];
    onChange(next);
  };
  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-base-content/70">{label}</p>
      <Field label="Колони (col_tracks)" hint="Пример: 1fr или 1fr 1fr (2 равни колони)">
        <Input value={bp.col_tracks || ''} onChange={v => update('col_tracks', v)} placeholder="1fr" />
      </Field>
      <Field label="Редове (row_tracks)" hint="Пример: auto 1fr auto">
        <Input value={bp.row_tracks || ''} onChange={v => update('row_tracks', v)} placeholder="auto 1fr auto" />
      </Field>
      <Field label="Зони (areas)" hint='Пример: "header" "main" "footer" — всяка в кавички, подредба от горе надолу'>
        <Input value={bp.areas || ''} onChange={v => update('areas', v)} placeholder='"header" "main" "footer"' />
      </Field>
      <div className="grid grid-cols-2 gap-2">
        <Field label="Gap X"><Input value={bp.gap_x || ''} onChange={v => update('gap_x', v)} placeholder="12px" /></Field>
        <Field label="Gap Y"><Input value={bp.gap_y || ''} onChange={v => update('gap_y', v)} placeholder="8px" /></Field>
      </div>
      <Field label="Padding" hint="Вътрешен отстъп на контейнера на този брейкпойнт">
        <Input value={bp.container_padding || ''} onChange={v => update('container_padding', v)} placeholder="0 12px" />
      </Field>
    </div>
  );
}

// ═══════════════════════════════════════════
// WIDGET CONFIGURATOR
// ═══════════════════════════════════════════
const WIDGET_CATALOG = [
  { type: 'logo', label: 'Site Logo', emoji: '🏠', category: 'branding' },
  { type: 'site_info', label: 'Site Info', emoji: '🏢', category: 'branding' },
  { type: 'search', label: 'Search Bar', emoji: '🔍', category: 'navigation' },
  { type: 'latest_from_category', label: 'Последен пост от категория', emoji: '📌', category: 'content', hasCount: true },
  { type: 'recent_posts', label: 'Последни постове', emoji: '📰', category: 'content', hasCount: true },
  { type: 'popular_posts', label: 'Популярни постове', emoji: '🔥', category: 'content', hasCount: true },
  { type: 'related_posts', label: 'Свързани постове', emoji: '🔗', category: 'content', hasCount: true },
  { type: 'category_tree', label: 'Категории', emoji: '📁', category: 'navigation' },
  { type: 'tag_cloud', label: 'Tag Cloud', emoji: '🏷', category: 'navigation' },
  { type: 'author_bio', label: 'Author Bio', emoji: '👤', category: 'content' },
  { type: 'newsletter', label: 'Newsletter Signup', emoji: '✉️', category: 'engagement' },
  { type: 'social_links', label: 'Social Links', emoji: '🌐', category: 'social' },
  { type: 'cta_banner', label: 'Call to Action', emoji: '📢', category: 'engagement' },
  { type: 'post_navigation', label: 'Post Navigation', emoji: '↔️', category: 'navigation' },
  { type: 'image', label: 'Image / Banner', emoji: '🖼', category: 'media' },
  { type: 'rich_text', label: 'Rich Text', emoji: '📝', category: 'content' },
  { type: 'custom_html', label: 'Custom HTML', emoji: '💻', category: 'advanced' },
  { type: 'copyright', label: 'Copyright', emoji: '©', category: 'branding' },
  { type: 'back_to_top', label: 'Back to Top', emoji: '⬆', category: 'navigation' },
];

const W_INPUT = 'w-full px-2 py-1.5 bg-base-200/60 border border-base-300 rounded-md text-xs text-base-content placeholder:text-base-content/30 focus:outline-none focus:border-primary';
const W_LABEL = 'block text-[10px] font-medium text-base-content/50 mb-1';
const W_CHECK = 'flex items-center gap-1.5 text-[10px] text-base-content/60';

function WidgetConfigurator({ widgets, sticky, onChange }: {
  widgets: Array<Record<string, unknown>>;
  sticky: boolean;
  onChange: (widgets: Array<Record<string, unknown>>, sticky: boolean) => void;
}) {
  const { siteId = '' } = useParams();
  const [showCatalog, setShowCatalog] = useState(false);
  const [expandedIdx, setExpandedIdx] = useState<number | null>(null);

  // Fetch categories for category selector
  const { data: categories } = useQuery<Array<{ id: string; name: string }>>({
    queryKey: ['categories', siteId],
    queryFn: () => categoriesApi.list(siteId).then((r: any) => r.data.data),
  });

  const addWidget = (type: string) => {
    const defaults: Record<string, Record<string, unknown>> = {
      latest_from_category: { type: 'latest_from_category', category_id: null, count: 1, title: 'Последен пост', content_mode: 'excerpt', excerpt_length: 200, show_image: true, show_date: true, show_category: true, include_children: false },
      recent_posts: { type: 'recent_posts', count: 5, title: 'Последни постове', show_date: true, show_category: false, category_id: null, include_children: false },
      popular_posts: { type: 'popular_posts', count: 5, title: 'Популярни постове', category_id: null, include_children: false },
      related_posts: { type: 'related_posts', count: 3 },
      category_tree: { type: 'category_tree', show_count: true },
      newsletter: { type: 'newsletter', title: 'Newsletter', description: 'Get updates in your inbox.' },
      cta_banner: { type: 'cta_banner', title: 'Ready to start?', button_text: 'Get Started', button_url: '/' },
      social_links: { type: 'social_links', links: [] },
      copyright: { type: 'copyright', text: '© {{year}} {{site_name}}. All rights reserved.' },
      image: { type: 'image', src: '', alt: '' },
      rich_text: { type: 'rich_text', content: '<p>Your content here</p>' },
      custom_html: { type: 'custom_html', html: '' },
      logo: { type: 'logo', url: '' },
    };
    const newWidgets = [...widgets, defaults[type] || { type }];
    onChange(newWidgets, sticky);
    setExpandedIdx(newWidgets.length - 1);
    setShowCatalog(false);
  };

  const removeWidget = (idx: number) => {
    onChange(widgets.filter((_, i) => i !== idx), sticky);
    if (expandedIdx === idx) setExpandedIdx(null);
  };
  const moveWidget = (idx: number, dir: -1 | 1) => {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= widgets.length) return;
    const arr = [...widgets];
    [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
    onChange(arr, sticky);
    if (expandedIdx === idx) setExpandedIdx(newIdx);
  };
  const updateWidget = (idx: number, updates: Record<string, unknown>) => {
    const arr = [...widgets];
    arr[idx] = { ...arr[idx], ...updates };
    onChange(arr, sticky);
  };

  // Category selector component
  const CategorySelect = ({ value, includeChildren, onChangeCategory, onChangeChildren }: {
    value: string | null; includeChildren: boolean;
    onChangeCategory: (v: string | null) => void; onChangeChildren: (v: boolean) => void;
  }) => (
    <div className="space-y-1.5">
      <label className={W_LABEL}>Категория</label>
      <select value={value || ''} onChange={e => onChangeCategory(e.target.value || null)} className={`${W_INPUT} bg-base-200`}>
        <option value="">Всички категории</option>
        {categories?.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
      </select>
      {value && (
        <label className={W_CHECK}>
          <input type="checkbox" checked={includeChildren} onChange={e => onChangeChildren(e.target.checked)}
            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
          Включи подкатегории
        </label>
      )}
    </div>
  );

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-warning">Widgets ({widgets.length})</span>
        <label className="flex items-center gap-1.5 text-xs text-base-content/50">
          <input type="checkbox" checked={sticky} onChange={e => onChange(widgets, e.target.checked)} className="checkbox checkbox-warning checkbox-xs" />
          Sticky
        </label>
      </div>
      <div className="space-y-1">
        {widgets.map((w, i) => {
          const cat = WIDGET_CATALOG.find(c => c.type === (w.type as string));
          const isExpanded = expandedIdx === i;
          const wType = w.type as string;

          return (
            <div key={i} className="bg-warning/5 border border-warning/25 rounded-lg overflow-hidden">
              <div className="flex items-center gap-2 p-2 text-xs cursor-pointer" onClick={() => setExpandedIdx(isExpanded ? null : i)}>
                <span className="text-base-content/40">{isExpanded ? '▾' : '▸'}</span>
                <span>{cat?.emoji || '📦'}</span>
                <span className="flex-1 font-medium text-base-content/80">
                  {(w.title as string)?.trim() || <span className="opacity-40 italic">{cat?.label || wType} (без заглавие)</span>}
                </span>
                <button onClick={e => { e.stopPropagation(); moveWidget(i, -1); }} className="text-base-content/30 hover:text-base-content/70 px-0.5">↑</button>
                <button onClick={e => { e.stopPropagation(); moveWidget(i, 1); }} className="text-base-content/30 hover:text-base-content/70 px-0.5">↓</button>
                <button onClick={e => { e.stopPropagation(); removeWidget(i); }} className="text-base-content/30 hover:text-error px-0.5">×</button>
              </div>

              {isExpanded && (
                <div className="px-3 pb-3 space-y-2 border-t border-warning/20 bg-base-200/30 pt-2">
                  {/* ── latest_from_category settings ── */}
                  {wType === 'latest_from_category' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className={W_INPUT} placeholder="Последен пост" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className={W_LABEL}>Брой постове</label>
                        <input type="number" min={1} max={10} value={(w.count as number) || 1} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 1 })}
                          className={W_INPUT} />
                      </div>
                      <div>
                        <label className={W_LABEL}>Съдържание</label>
                        <select value={(w.content_mode as string) || 'excerpt'} onChange={e => updateWidget(i, { content_mode: e.target.value })}
                          className={`${W_INPUT} bg-base-200`}>
                          <option value="none">Без съдържание (само заглавие)</option>
                          <option value="excerpt">Excerpt (кратък текст)</option>
                          <option value="full">Цяло съдържание (всички блокове)</option>
                        </select>
                        <p className="text-[9px] text-base-content/40 mt-0.5">
                          {(w.content_mode as string) === 'full'
                            ? 'Показва целия пост — всички блокове, изображения, текст и т.н.'
                            : (w.content_mode as string) === 'none'
                            ? 'Показва само заглавие, дата и категория.'
                            : 'Показва кратък текст. Ако няма ръчен excerpt, автоматично се извлича от първия текстов блок.'}
                        </p>
                      </div>
                      {((w.content_mode as string) || 'excerpt') === 'excerpt' && (
                        <div>
                          <label className={W_LABEL}>Дължина на excerpt (символи)</label>
                          <input type="number" min={50} max={2000} step={50} value={(w.excerpt_length as number) || 200} onChange={e => updateWidget(i, { excerpt_length: parseInt(e.target.value) || 200 })}
                            className={W_INPUT} />
                          <p className="text-[9px] text-base-content/40 mt-0.5">Максимален брой символи. По-дълъг текст се отрязва с "..."</p>
                        </div>
                      )}
                      <div className="space-y-1">
                        <label className={W_CHECK}>
                          <input type="checkbox" checked={(w.show_image as boolean) ?? true} onChange={e => updateWidget(i, { show_image: e.target.checked })}
                            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
                          Покажи featured image
                        </label>
                        <label className={W_CHECK}>
                          <input type="checkbox" checked={(w.show_category as boolean) ?? true} onChange={e => updateWidget(i, { show_category: e.target.checked })}
                            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
                          Покажи категория
                        </label>
                        <label className={W_CHECK}>
                          <input type="checkbox" checked={(w.show_date as boolean) ?? true} onChange={e => updateWidget(i, { show_date: e.target.checked })}
                            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
                          Покажи дата
                        </label>
                      </div>
                    </>
                  )}

                  {/* ── recent_posts settings ── */}
                  {wType === 'recent_posts' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className={W_INPUT} placeholder="Последни постове" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className={W_LABEL}>Брой</label>
                        <input type="number" min={1} max={20} value={(w.count as number) || 5} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 5 })}
                          className={W_INPUT} />
                      </div>
                      <div className="space-y-1">
                        <label className={W_CHECK}>
                          <input type="checkbox" checked={(w.show_date as boolean) ?? true} onChange={e => updateWidget(i, { show_date: e.target.checked })}
                            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
                          Покажи дата
                        </label>
                        <label className={W_CHECK}>
                          <input type="checkbox" checked={(w.show_category as boolean) ?? false} onChange={e => updateWidget(i, { show_category: e.target.checked })}
                            className="checkbox checkbox-primary" style={{ width: 12, height: 12 }} />
                          Покажи категория
                        </label>
                      </div>
                    </>
                  )}

                  {/* ── popular_posts settings ── */}
                  {wType === 'popular_posts' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className={W_INPUT} placeholder="Популярни постове" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className={W_LABEL}>Брой</label>
                        <input type="number" min={1} max={20} value={(w.count as number) || 5} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 5 })}
                          className={W_INPUT} />
                      </div>
                    </>
                  )}

                  {/* ── related_posts settings ── */}
                  {wType === 'related_posts' && (
                    <div>
                      <label className={W_LABEL}>Брой</label>
                      <input type="number" min={1} max={10} value={(w.count as number) || 3} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 3 })}
                        className={W_INPUT} />
                    </div>
                  )}

                  {/* ── newsletter settings ── */}
                  {wType === 'newsletter' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })} className={W_INPUT} />
                      </div>
                      <div>
                        <label className={W_LABEL}>Описание</label>
                        <input value={(w.description as string) || ''} onChange={e => updateWidget(i, { description: e.target.value })} className={W_INPUT} />
                      </div>
                    </>
                  )}

                  {/* ── cta_banner settings ── */}
                  {wType === 'cta_banner' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })} className={W_INPUT} />
                      </div>
                      <div>
                        <label className={W_LABEL}>Бутон текст</label>
                        <input value={(w.button_text as string) || ''} onChange={e => updateWidget(i, { button_text: e.target.value })} className={W_INPUT} />
                      </div>
                      <div>
                        <label className={W_LABEL}>Бутон URL</label>
                        <input value={(w.button_url as string) || ''} onChange={e => updateWidget(i, { button_url: e.target.value })} className={W_INPUT} />
                      </div>
                    </>
                  )}

                  {/* ── custom_html settings ── */}
                  {wType === 'custom_html' && (
                    <div>
                      <label className={W_LABEL}>HTML код</label>
                      <textarea value={(w.html as string) || ''} onChange={e => updateWidget(i, { html: e.target.value })} rows={4}
                        className={`${W_INPUT} font-mono`} />
                    </div>
                  )}

                  {/* ── rich_text settings ── */}
                  {wType === 'rich_text' && (
                    <div>
                      <label className={W_LABEL}>Съдържание (HTML)</label>
                      <textarea value={(w.content as string) || ''} onChange={e => updateWidget(i, { content: e.target.value })} rows={4}
                        className={W_INPUT} />
                    </div>
                  )}

                  {/* ── logo settings ── */}
                  {wType === 'logo' && (
                    <div>
                      <label className={W_LABEL}>Logo URL</label>
                      <input value={(w.url as string) || ''} onChange={e => updateWidget(i, { url: e.target.value })} className={W_INPUT} placeholder="https://..." />
                    </div>
                  )}

                  {/* ── image settings ── */}
                  {wType === 'image' && (
                    <>
                      <div>
                        <label className={W_LABEL}>Image URL</label>
                        <input value={(w.src as string) || ''} onChange={e => updateWidget(i, { src: e.target.value })} className={W_INPUT} placeholder="https://..." />
                      </div>
                      <div>
                        <label className={W_LABEL}>Alt текст</label>
                        <input value={(w.alt as string) || ''} onChange={e => updateWidget(i, { alt: e.target.value })} className={W_INPUT} />
                      </div>
                      <div>
                        <label className={W_LABEL}>Линк (optional)</label>
                        <input value={(w.link as string) || ''} onChange={e => updateWidget(i, { link: e.target.value })} className={W_INPUT} placeholder="https://..." />
                      </div>
                    </>
                  )}

                  {/* ── copyright settings ── */}
                  {wType === 'copyright' && (
                    <div>
                      <label className={W_LABEL}>Текст</label>
                      <input value={(w.text as string) || ''} onChange={e => updateWidget(i, { text: e.target.value })} className={W_INPUT} placeholder="© {{year}} {{site_name}}" />
                      <p className="text-[9px] text-base-content/40 mt-0.5">Използвай {'{{year}}'} и {'{{site_name}}'} за автоматични стойности</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
      {showCatalog ? (
        <div className="border border-warning/25 rounded-lg overflow-hidden">
          <div className="bg-warning/10 px-3 py-2 flex items-center justify-between border-b border-warning/20">
            <span className="text-xs font-medium text-warning">Добави Widget</span>
            <button onClick={() => setShowCatalog(false)} className="text-xs text-base-content/50 hover:text-base-content">Отказ</button>
          </div>
          <div className="max-h-60 overflow-y-auto p-1">
            {WIDGET_CATALOG.map(w => (
              <button key={w.type} onClick={() => addWidget(w.type)}
                className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-base-200 rounded-md text-xs transition-colors">
                <span>{w.emoji}</span>
                <span className="font-medium text-base-content/80">{w.label}</span>
                <span className="ml-auto text-base-content/25 text-[10px]">{w.category}</span>
              </button>
            ))}
          </div>
        </div>
      ) : (
        <button onClick={() => setShowCatalog(true)}
          className="w-full px-3 py-2 text-xs font-medium text-warning border border-warning/40 border-dashed rounded-lg hover:bg-warning/10 transition-colors">
          + Добави Widget
        </button>
      )}
    </div>
  );
}
