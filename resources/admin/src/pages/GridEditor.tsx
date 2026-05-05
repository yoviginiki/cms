import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, LayoutGrid, Plus, Minus, MousePointer, Eraser, Trash2,
  Settings, Palette, Columns, Smartphone, Monitor, Eye, ChevronDown, ChevronRight,
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
  canvas: '#22c55e', menu: '#3b82f6', query: '#a855f7',
  fixed: '#6b7280', widget: '#f59e0b', static: '#f97316', '': '#d1d5db',
};
const TYPE_LABELS: Record<string, string> = {
  canvas: 'Блоково съдържание (per page)', menu: 'Навигационно меню', query: 'Динамичен списък постове',
  fixed: 'Фиксирано (еднакво навсякъде)', widget: 'Уиджет колона', static: 'Авто-генерирано',
};

// ─── Column presets ───
const COL_PRESETS = [
  { label: '1 колона', cols: ['1fr'], rows: 1 },
  { label: '2 равни', cols: ['1fr', '1fr'], rows: 2 },
  { label: '3 равни', cols: ['1fr', '1fr', '1fr'], rows: 3 },
  { label: '4 равни', cols: ['1fr', '1fr', '1fr', '1fr'], rows: 4 },
  { label: '⅓ + ⅔', cols: ['1fr', '2fr'], rows: 2 },
  { label: '⅔ + ⅓', cols: ['2fr', '1fr'], rows: 2 },
  { label: '¼ + ¾', cols: ['1fr', '3fr'], rows: 2 },
  { label: '¾ + ¼', cols: ['3fr', '1fr'], rows: 2 },
  { label: '¼ + ½ + ¼', cols: ['1fr', '2fr', '1fr'], rows: 3 },
  { label: '250px + auto', cols: ['250px', '1fr'], rows: 2 },
  { label: 'auto + 300px', cols: ['1fr', '300px'], rows: 2 },
  { label: '200px + auto + 300px', cols: ['200px', '1fr', '300px'], rows: 3 },
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

// ─── Collapsible section ───
function Section({ title, icon, children, defaultOpen = false, hint }: {
  title: string; icon?: React.ReactNode; children: React.ReactNode; defaultOpen?: boolean; hint?: string;
}) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border border-gray-100 rounded-lg overflow-hidden">
      <button onClick={() => setOpen(!open)}
        className="w-full flex items-center gap-2 px-3 py-2.5 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
        {open ? <ChevronDown size={14} className="text-gray-400" /> : <ChevronRight size={14} className="text-gray-400" />}
        {icon}
        <span className="text-xs font-semibold text-gray-700 flex-1">{title}</span>
      </button>
      {hint && open && <p className="px-3 py-1 text-[10px] text-gray-400 bg-gray-50 border-b border-gray-100">{hint}</p>}
      {open && <div className="p-3 space-y-3">{children}</div>}
    </div>
  );
}

// ─── Small labeled input ───
function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-[11px] font-medium text-gray-500 mb-1">{label}</label>
      {children}
      {hint && <p className="text-[10px] text-gray-400 mt-0.5">{hint}</p>}
    </div>
  );
}
function Input({ value, onChange, placeholder, className = '' }: {
  value: string; onChange: (v: string) => void; placeholder?: string; className?: string;
}) {
  return (
    <input value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
      className={`w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ${className}`} />
  );
}
function Select({ value, onChange, options, className = '' }: {
  value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; className?: string;
}) {
  return (
    <select value={value} onChange={e => onChange(e.target.value)}
      className={`w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white ${className}`}>
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  );
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
  const [cols, setCols] = useState(4);
  const [rows, setRows] = useState(5);
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

  // Drag selection state
  const [dragStart, setDragStart] = useState<{r: number; c: number} | null>(null);
  const [dragEnd, setDragEnd] = useState<{r: number; c: number} | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const gridRef = useRef<HTMLDivElement>(null);

  const dirty = () => setIsDirty(true);

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
        setRows(areaRows.length);
        setCols(areaRows[0].length);
        setColSizes(gridData.col_tracks.split(/\s+/));
        setRowSizes(gridData.row_tracks.split(/\s+/));
      } else {
        resetGrid(4, 5);
      }
    }
  }, [gridData]);

  const resetGrid = (c: number, r: number) => {
    setCells(Array.from({ length: r }, () => Array(c).fill('.')));
    setCols(c); setRows(r);
    setColSizes(Array(c).fill('1fr'));
    setRowSizes(Array(r).fill('auto'));
  };

  // Derived
  const areaNames = (() => {
    const s = new Set<string>();
    cells.forEach(row => row.forEach(c => { if (c !== '.') s.add(c); }));
    return Array.from(s);
  })();

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
  const isInSelection = (r: number, c: number) => {
    const rect = getSelectionRect();
    if (!rect) return false;
    return r >= rect.r1 && r <= rect.r2 && c >= rect.c1 && c <= rect.c2;
  };

  // Finish drag
  const finishDrag = () => {
    const rect = getSelectionRect();
    if (!rect) { setDragStart(null); setDragEnd(null); setIsDragging(false); return; }

    if (mode === 'erase') {
      const n = cells.map(row => [...row]);
      for (let r = rect.r1; r <= rect.r2; r++)
        for (let c = rect.c1; c <= rect.c2; c++) n[r][c] = '.';
      setCells(n); dirty();
      setDragStart(null); setDragEnd(null); setIsDragging(false);
      return;
    }

    const areaName = window.prompt('Име на зоната (малки букви, напр. header, sidebar, content, banner):');
    if (!areaName || !/^[a-z][a-z0-9-]*$/.test(areaName)) {
      setDragStart(null); setDragEnd(null); setIsDragging(false);
      return;
    }

    const n = cells.map(row => [...row]);
    for (let r = rect.r1; r <= rect.r2; r++)
      for (let c = rect.c1; c <= rect.c2; c++) n[r][c] = areaName;
    setCells(n);

    if (!positions.find(p => p.area_name === areaName)) {
      setPositions(prev => [...prev, {
        area_name: areaName,
        label: areaName.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        type: 'canvas', config_json: {}, scope: 'page',
        is_overridable: false, mobile_order: prev.length + 1,
      }]);
    }

    setSelectedArea(areaName);
    setRightTab('area-config');
    dirty();
    setDragStart(null); setDragEnd(null); setIsDragging(false);
  };

  // Grid mutation helpers
  const addCol = () => { setCells(p => p.map(r => [...r, '.'])); setCols(c => c+1); setColSizes(p => [...p, '1fr']); dirty(); };
  const rmCol = () => { if (cols <= 1) return; setCells(p => p.map(r => r.slice(0,-1))); setCols(c => c-1); setColSizes(p => p.slice(0,-1)); dirty(); };
  const addRow = () => { setCells(p => [...p, Array(cols).fill('.')]); setRows(r => r+1); setRowSizes(p => [...p, 'auto']); dirty(); };
  const rmRow = () => { if (rows <= 1) return; setCells(p => p.slice(0,-1)); setRows(r => r-1); setRowSizes(p => p.slice(0,-1)); dirty(); };

  const deleteArea = (area: string) => {
    setCells(prev => prev.map(row => row.map(c => c === area ? '.' : c)));
    setPositions(prev => prev.filter(p => p.area_name !== area));
    if (selectedArea === area) setSelectedArea(null);
    dirty();
  };

  const updatePosition = (area: string, updates: Partial<Position>) => {
    setPositions(p => p.map(pos => pos.area_name === area ? { ...pos, ...updates } : pos));
    dirty();
  };

  const applyColPreset = (preset: typeof COL_PRESETS[0]) => {
    const newCols = preset.cols.length;
    setColSizes(preset.cols);
    setCols(newCols);
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
      // Auto-publish site after grid save
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

  const sel = selectedArea ? positions.find(p => p.area_name === selectedArea) : null;

  if (isLoading) return <div className="flex items-center justify-center h-screen"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  return (
    <div className="flex flex-col h-screen bg-gray-50 select-none">
      {/* ─── Toolbar ─── */}
      <div className="flex items-center justify-between px-4 py-2 bg-white border-b shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/grids`)} className="p-1.5 hover:bg-gray-100 rounded-md"><ArrowLeft size={18} /></button>
          <input value={name} onChange={e => { setName(e.target.value); dirty(); }} className="text-lg font-semibold bg-transparent border-none outline-none" />
          {isDirty && <span className="text-xs text-orange-500 font-medium">Unsaved</span>}
        </div>
        <div className="flex items-center gap-2">
          {publishStatus === 'publishing' && <span className="text-xs text-yellow-600 flex items-center gap-1"><Loader2 size={12} className="animate-spin" /> Публикуване...</span>}
          {publishStatus === 'done' && <span className="text-xs text-green-600">Публикувано</span>}
          {publishStatus === 'error' && <span className="text-xs text-red-500">Publish грешка</span>}
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !isDirty}
            className="flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {saveMutation.isPending ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />} Запази & Публикувай
          </button>
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* ═══ LEFT: Grid canvas ═══ */}
        <div className="flex-1 p-4 overflow-auto">
          {/* Controls */}
          <div className="flex flex-wrap items-center gap-2 mb-3">
            <div className="flex items-center gap-1 bg-white rounded-lg border px-2 py-1 text-xs">
              <button onClick={rmCol} className="p-0.5 hover:bg-gray-100 rounded"><Minus size={12} /></button>
              <span className="w-8 text-center font-bold">{cols}col</span>
              <button onClick={addCol} className="p-0.5 hover:bg-gray-100 rounded"><Plus size={12} /></button>
            </div>
            <div className="flex items-center gap-1 bg-white rounded-lg border px-2 py-1 text-xs">
              <button onClick={rmRow} className="p-0.5 hover:bg-gray-100 rounded"><Minus size={12} /></button>
              <span className="w-8 text-center font-bold">{rows}row</span>
              <button onClick={addRow} className="p-0.5 hover:bg-gray-100 rounded"><Plus size={12} /></button>
            </div>
            <div className="h-5 w-px bg-gray-200" />
            <button onClick={() => setMode('select')}
              className={`flex items-center gap-1 px-2 py-1 text-xs rounded-lg border ${mode === 'select' ? 'bg-blue-50 border-blue-300 text-blue-700' : 'border-gray-200 text-gray-500'}`}>
              <MousePointer size={12} /> Чертай
            </button>
            <button onClick={() => setMode('erase')}
              className={`flex items-center gap-1 px-2 py-1 text-xs rounded-lg border ${mode === 'erase' ? 'bg-red-50 border-red-300 text-red-700' : 'border-gray-200 text-gray-500'}`}>
              <Eraser size={12} /> Изтрий
            </button>
          </div>

          {/* Column presets */}
          <div className="flex flex-wrap gap-1 mb-3">
            {COL_PRESETS.map(p => (
              <button key={p.label} onClick={() => applyColPreset(p)}
                className="px-2 py-0.5 text-[10px] bg-white border border-gray-200 rounded hover:bg-blue-50 hover:border-blue-300 transition-colors">
                {p.label}
              </button>
            ))}
          </div>

          {/* Column size ruler */}
          <div className="flex mb-1 ml-12 gap-px">
            {colSizes.map((s, i) => (
              <input key={i} value={s} onChange={e => { const n = [...colSizes]; n[i] = e.target.value; setColSizes(n); dirty(); }}
                title="CSS стойност: 1fr, 2fr, 300px, 25%, minmax(200px,1fr), auto"
                className="flex-1 text-center text-[10px] font-mono text-gray-400 bg-gray-100 rounded px-0.5 py-0.5 border min-w-0" />
            ))}
          </div>

          {/* Grid + row ruler */}
          <div className="flex">
            <div className="flex flex-col mr-1 gap-px">
              {rowSizes.map((s, i) => (
                <div key={i} className="flex items-center" style={{ height: rowSizes[i] === '1fr' ? '100px' : '60px' }}>
                  <input value={s} onChange={e => { const n = [...rowSizes]; n[i] = e.target.value; setRowSizes(n); dirty(); }}
                    title="CSS стойност: auto, 1fr, 100px, 50vh, minmax(80px,auto)"
                    className="text-[10px] font-mono text-gray-400 bg-gray-100 rounded px-0.5 py-0.5 border w-11 text-center" />
                </div>
              ))}
            </div>

            {/* THE GRID */}
            <div ref={gridRef}
              className={`flex-1 bg-white rounded-xl border-2 shadow-sm overflow-hidden ${mode === 'erase' ? 'border-red-200' : 'border-gray-200'}`}
              onMouseLeave={() => { if (isDragging) finishDrag(); }}
              onMouseUp={() => { if (isDragging) finishDrag(); }}>
              <div style={{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)`, gap: '1px', padding: '1px', background: '#e5e7eb' }}>
                {cells.flatMap((row, ri) => row.map((cell, ci) => {
                  const active = cell !== '.';
                  const pos = positions.find(p => p.area_name === cell);
                  const color = active ? TYPE_COLORS[pos?.type ?? ''] : '#f9fafb';
                  const inSel = isDragging && isInSelection(ri, ci);
                  const isSelArea = selectedArea === cell && active;
                  let isFirst = false;
                  if (active) {
                    isFirst = true;
                    for (let r = 0; r < cells.length && isFirst; r++)
                      for (let c = 0; c < cells[r].length && isFirst; c++)
                        if (cells[r][c] === cell && (r < ri || (r === ri && c < ci))) isFirst = false;
                  }

                  return (
                    <div key={`${ri}-${ci}`}
                      className={`relative flex items-center justify-center transition-all ${isSelArea ? 'ring-2 ring-blue-500 ring-inset' : ''}`}
                      style={{
                        backgroundColor: inSel ? (mode === 'erase' ? '#fee2e2' : '#dbeafe') : (active ? color + '18' : '#f9fafb'),
                        minHeight: rowSizes[ri] === '1fr' ? '100px' : '60px',
                        cursor: 'crosshair',
                      }}
                      onMouseDown={e => {
                        e.preventDefault();
                        setDragStart({ r: ri, c: ci }); setDragEnd({ r: ri, c: ci }); setIsDragging(true);
                        if (active && mode === 'select') { setSelectedArea(cell); setRightTab('area-config'); }
                      }}
                      onMouseEnter={() => { if (isDragging) setDragEnd({ r: ri, c: ci }); }}
                      onClick={() => { if (!isDragging && active) { setSelectedArea(cell); setRightTab('area-config'); } }}
                    >
                      {active && isFirst && (
                        <div className="text-center z-10 pointer-events-none">
                          <div className="text-xs font-bold leading-tight" style={{ color }}>{cell}</div>
                          <div className="text-[9px] leading-tight" style={{ color: color + 'aa' }}>{pos?.type}</div>
                        </div>
                      )}
                      {!active && !inSel && <span className="text-gray-200 text-lg">·</span>}
                      {inSel && !active && (
                        <span className={`text-xs font-bold ${mode === 'erase' ? 'text-red-300' : 'text-blue-300'}`}>
                          {mode === 'erase' ? '×' : '+'}
                        </span>
                      )}
                    </div>
                  );
                }))}
              </div>
            </div>
          </div>

          <div className="mt-2 flex items-center gap-4 text-xs text-gray-400">
            <span><strong>Чертай:</strong> влачи правоъгълник → дай му име</span>
            <span><strong>Изтрий:</strong> превключи на гумичка → влачи за изтриване</span>
            <span><strong>Настрой:</strong> кликни зона → десен панел</span>
          </div>
        </div>

        {/* ═══ RIGHT: Config panel ═══ */}
        <div className="w-96 bg-white border-l overflow-y-auto shrink-0">
          {/* Tab switcher */}
          <div className="flex border-b sticky top-0 bg-white z-10">
            <button onClick={() => { setRightTab('grid-settings'); setSelectedArea(null); }}
              className={`flex-1 px-3 py-2.5 text-xs font-medium transition-colors ${rightTab === 'grid-settings' ? 'border-b-2 border-blue-500 text-blue-700' : 'text-gray-500 hover:text-gray-700'}`}>
              <Settings size={12} className="inline mr-1" /> Настройки грид
            </button>
            <button onClick={() => setRightTab('area-config')}
              className={`flex-1 px-3 py-2.5 text-xs font-medium transition-colors ${rightTab === 'area-config' ? 'border-b-2 border-blue-500 text-blue-700' : 'text-gray-500 hover:text-gray-700'}`}>
              <Columns size={12} className="inline mr-1" /> {sel ? sel.label : 'Зони'}
            </button>
          </div>

          {rightTab === 'grid-settings' && (
            <div className="p-3 space-y-3">
              {/* ── Container ── */}
              <Section title="Контейнер" icon={<Monitor size={13} className="text-gray-400" />} defaultOpen={true}
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
                <label className="flex items-center gap-2 p-2.5 bg-indigo-50 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={fullBleed} onChange={e => { setFullBleed(e.target.checked); dirty(); }}
                    className="rounded border-gray-300 text-indigo-600" />
                  <div>
                    <p className="text-xs font-medium text-indigo-700">Full-bleed обвивка</p>
                    <p className="text-[10px] text-indigo-500">Фонът на грида покрива целия екран, съдържанието остава центрирано</p>
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
              <Section title="Подравняване (Alignment)" icon={<LayoutGrid size={13} className="text-gray-400" />}
                hint="Как се подравнява съдържанието вътре в зоните. Stretch = запълва цялата зона.">
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Вертикално (align-items)" hint="Подравняване по вертикала на съдържанието в зоните">
                    <Select value={alignItems} onChange={v => { setAlignItems(v); dirty(); }} options={ALIGN_OPTIONS} />
                  </Field>
                  <Field label="Хоризонтално (justify-items)" hint="Подравняване по хоризонтала">
                    <Select value={justifyItems} onChange={v => { setJustifyItems(v); dirty(); }} options={ALIGN_OPTIONS} />
                  </Field>
                </div>
              </Section>

              {/* ── Layout Mode ── */}
              <Section title="Режим на страницата" icon={<Eye size={13} className="text-gray-400" />}
                hint="Определя как се показва страницата — нормално скролиране, хоризонтален слайдер, или snap секции на цял екран.">
                <div className="space-y-1.5">
                  {[
                    { value: 'default', label: 'Нормален', desc: 'Стандартно вертикално скролиране' },
                    { value: 'horizontal-scroll', label: 'Хоризонтален скрол', desc: 'Зоните се редят хоризонтално, всяка заема цял екран. Scroll snap за плавно превъртане.' },
                    { value: 'snap-sections', label: 'Snap секции (вертикални)', desc: 'Всяка зона е на цял екран. При скролиране щраква на следващата секция.' },
                  ].map(m => (
                    <button key={m.value} onClick={() => { setLayoutMode(m.value); dirty(); }}
                      className={`w-full text-left px-3 py-2.5 rounded-lg border-2 transition-all ${layoutMode === m.value ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200'}`}>
                      <div className="text-xs font-medium">{m.label}</div>
                      <p className="text-[10px] text-gray-400 mt-0.5">{m.desc}</p>
                    </button>
                  ))}
                </div>
              </Section>

              {/* ── Background ── */}
              <Section title="Фон на грида" icon={<Palette size={13} className="text-gray-400" />}
                hint="Фон на целия грид контейнер. Работи добре с full-bleed.">
                <Field label="Цвят">
                  <div className="flex gap-2">
                    <input type="color" value={bgJson.color || '#ffffff'} onChange={e => { setBgJson({ ...bgJson, color: e.target.value }); dirty(); }}
                      className="w-8 h-8 rounded border cursor-pointer" />
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
                  <button onClick={() => { setBgJson({}); dirty(); }} className="text-xs text-red-500 hover:underline">Изчисти фон</button>
                )}
              </Section>

              {/* ── Responsive Breakpoints ── */}
              <Section title="Responsive (Tablet / Mobile)" icon={<Smartphone size={13} className="text-gray-400" />}
                hint="Различна подредба за таблет (≤1024px) и мобилен (≤768px). Ако не зададеш, колоните стават 1fr на мобилен.">
                <BreakpointEditor label="Таблет (≤1024px)" bp={breakpointsJson.tablet || {}}
                  onChange={v => { setBreakpointsJson({ ...breakpointsJson, tablet: v }); dirty(); }} />
                <div className="border-t my-2" />
                <BreakpointEditor label="Мобилен (≤768px)" bp={breakpointsJson.mobile || {}}
                  onChange={v => { setBreakpointsJson({ ...breakpointsJson, mobile: v }); dirty(); }} />
              </Section>

              {/* ── Areas list ── */}
              <Section title={`Зони (${areaNames.length})`} defaultOpen={true}>
                {areaNames.length === 0 ? (
                  <div className="text-center py-8 text-gray-300">
                    <LayoutGrid className="h-8 w-8 mx-auto mb-2" />
                    <p className="text-xs">Начертай зони на грида</p>
                  </div>
                ) : (
                  <div className="space-y-1">
                    {areaNames.map(a => {
                      const p = positions.find(pos => pos.area_name === a);
                      const cnt = cells.flat().filter(c => c === a).length;
                      const color = TYPE_COLORS[p?.type ?? ''];
                      return (
                        <button key={a} onClick={() => { setSelectedArea(a); setRightTab('area-config'); }}
                          className={`w-full flex items-center gap-2 px-3 py-2 rounded-lg border-2 text-left transition-all ${selectedArea === a ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200'}`}>
                          <span className="w-3 h-3 rounded shrink-0" style={{ backgroundColor: color + '30', border: `2px solid ${color}` }} />
                          <div className="flex-1 min-w-0">
                            <div className="text-xs font-medium text-gray-900">{p?.label || a}</div>
                            <div className="text-[10px] text-gray-400">{p?.type} · {cnt} клетки · {p?.scope}</div>
                          </div>
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
                <div>
                  <h3 className="font-semibold text-gray-900 text-base">{sel.label}</h3>
                  <p className="text-[10px] text-gray-400 font-mono">{selectedArea}</p>
                </div>
                <div className="flex gap-1">
                  <button onClick={() => deleteArea(selectedArea!)} className="p-1.5 text-gray-400 hover:text-red-500 rounded" title="Изтрий зона"><Trash2 size={14} /></button>
                  <button onClick={() => { setSelectedArea(null); setRightTab('grid-settings'); }} className="text-xs text-gray-400 hover:text-gray-600 px-2">✕</button>
                </div>
              </div>

              {/* ── Content Type ── */}
              <Section title="Тип съдържание" defaultOpen={true}
                hint="Определя какво се показва в тази зона — блоков редактор, меню, списък постове, и т.н.">
                <Field label="Име">
                  <Input value={sel.label} onChange={v => updatePosition(selectedArea!, { label: v })} />
                </Field>
                <div className="space-y-1.5">
                  {(['canvas','menu','query','fixed','widget','static'] as PositionType[]).map(t => (
                    <button key={t} onClick={() => updatePosition(selectedArea!, { type: t })}
                      className={`w-full text-left px-3 py-2 rounded-lg border-2 transition-all ${sel.type === t ? 'border-blue-500 bg-blue-50' : 'border-gray-100 hover:border-gray-200'}`}>
                      <div className="flex items-center gap-2">
                        <span className="w-3 h-3 rounded-full shrink-0" style={{ backgroundColor: TYPE_COLORS[t] }} />
                        <span className="text-xs font-medium capitalize">{t}</span>
                      </div>
                      <p className="text-[10px] text-gray-400 ml-5 mt-0.5">{TYPE_LABELS[t]}</p>
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
                <label className="flex items-center gap-2 p-2.5 bg-gray-50 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={sel.is_overridable} onChange={e => updatePosition(selectedArea!, { is_overridable: e.target.checked })}
                    className="rounded border-gray-300 text-blue-600" />
                  <div>
                    <p className="text-xs font-medium text-gray-700">Overridable</p>
                    <p className="text-[10px] text-gray-400">Позволява на отделни страници да заменят съдържанието</p>
                  </div>
                </label>
                <Field label="Mobile stack order" hint="По-малко число = по-нагоре на мобилен. Определя реда при едноколонов layout.">
                  <Input value={String(sel.mobile_order)} onChange={v => updatePosition(selectedArea!, { mobile_order: parseInt(v) || 0 })} />
                </Field>
              </Section>

              {/* ── Size & Layout ── */}
              <Section title="Размери и подравняване"
                hint="Контролира минимална/максимална височина/ширина и как се подравнява съдържанието вътре в зоната.">
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Мин. височина" hint="Примери: 100vh, 400px, auto">
                    <Input value={sel.min_height || ''} onChange={v => updatePosition(selectedArea!, { min_height: v || undefined })} placeholder="auto" />
                  </Field>
                  <Field label="Макс. ширина" hint="Примери: 800px, 60%, none">
                    <Input value={sel.max_width || ''} onChange={v => updatePosition(selectedArea!, { max_width: v || undefined })} placeholder="none" />
                  </Field>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <Field label="Верт. подравняване" hint="Как се позиционира зоната вертикално в своята клетка">
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
                <label className="flex items-center gap-2 p-2.5 bg-indigo-50 rounded-lg cursor-pointer">
                  <input type="checkbox" checked={sel.full_bleed || false} onChange={e => updatePosition(selectedArea!, { full_bleed: e.target.checked })}
                    className="rounded border-gray-300 text-indigo-600" />
                  <div>
                    <p className="text-xs font-medium text-indigo-700">Full-bleed зона</p>
                    <p className="text-[10px] text-indigo-500">Зоната излиза извън контейнера и покрива целия екран по ширина</p>
                  </div>
                </label>
              </Section>

              {/* ── Padding ── */}
              <Section title="Padding (вътрешен отстъп)"
                hint="Разстоянието вътре в зоната между ръба и съдържанието. Можеш да ползваш px, %, rem.">
                <PaddingEditor value={sel.padding_json || {}} onChange={v => updatePosition(selectedArea!, { padding_json: v })} />
              </Section>

              {/* ── Background ── */}
              <Section title="Фон на зоната" icon={<Palette size={13} className="text-gray-400" />}
                hint="Цвят, градиент или изображение зад съдържанието на тази зона.">
                <Field label="Цвят">
                  <div className="flex gap-2">
                    <input type="color" value={sel.background_json?.color || '#ffffff'} onChange={e => updatePosition(selectedArea!, { background_json: { ...sel.background_json, color: e.target.value } })}
                      className="w-8 h-8 rounded border cursor-pointer" />
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
                        className={`px-2 py-0.5 text-[10px] rounded border ${sel.shadow === s.value ? 'bg-blue-50 border-blue-300 text-blue-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50'}`}>
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
                  <div className="text-xs text-gray-600">
                    <p>Тази зона показва едно и също съдържание на всяка страница.</p>
                    <p className="mt-1">Можеш да зададеш Blade partial:</p>
                    <Input value={(sel.config_json as any).blade_partial || ''} onChange={v => updatePosition(selectedArea!, { config_json: { ...sel.config_json, blade_partial: v } })}
                      placeholder="напр. header, footer" />
                  </div>
                </Section>
              )}
            </div>
          )}

          {rightTab === 'area-config' && !sel && (
            <div className="p-4 text-center text-gray-400 py-20">
              <LayoutGrid className="h-10 w-10 mx-auto mb-3 opacity-50" />
              <p className="text-sm">Кликни на зона от грида</p>
              <p className="text-xs mt-1">или начертай нова зона с мишката</p>
            </div>
          )}
        </div>
      </div>
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
          <button key={p.label} onClick={() => onChange(p.v)}
            className="px-2 py-0.5 text-[10px] rounded border border-gray-200 text-gray-500 hover:bg-blue-50 hover:border-blue-300">
            {p.label}
          </button>
        ))}
      </div>
      <div className="grid grid-cols-4 gap-1">
        {(['top', 'right', 'bottom', 'left'] as const).map(s => (
          <div key={s}>
            <label className="block text-[10px] text-gray-400 text-center mb-0.5">{s}</label>
            <input value={value[s] || '0'} onChange={e => update(s, e.target.value)}
              className="w-full text-center text-[11px] border rounded px-1 py-1" placeholder="0" />
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
      <p className="text-xs font-medium text-gray-600">{label}</p>
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
      <label className="block text-[10px] font-medium text-gray-500">Категория</label>
      <select value={value || ''} onChange={e => onChangeCategory(e.target.value || null)}
        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs bg-white">
        <option value="">Всички категории</option>
        {categories?.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
      </select>
      {value && (
        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
          <input type="checkbox" checked={includeChildren} onChange={e => onChangeChildren(e.target.checked)}
            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
          Включи подкатегории
        </label>
      )}
    </div>
  );

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-yellow-700">Widgets ({widgets.length})</span>
        <label className="flex items-center gap-1.5 text-xs text-gray-500">
          <input type="checkbox" checked={sticky} onChange={e => onChange(widgets, e.target.checked)} className="rounded border-gray-300 text-yellow-600" />
          Sticky
        </label>
      </div>
      <div className="space-y-1">
        {widgets.map((w, i) => {
          const cat = WIDGET_CATALOG.find(c => c.type === (w.type as string));
          const isExpanded = expandedIdx === i;
          const wType = w.type as string;

          return (
            <div key={i} className="bg-yellow-50 border border-yellow-200 rounded-lg overflow-hidden">
              <div className="flex items-center gap-2 p-2 text-xs cursor-pointer" onClick={() => setExpandedIdx(isExpanded ? null : i)}>
                <span>{isExpanded ? '▾' : '▸'}</span>
                <span>{cat?.emoji || '📦'}</span>
                <span className="flex-1 font-medium text-yellow-800">
                  {(w.title as string)?.trim() || <span className="opacity-40 italic">{cat?.label || wType} (без заглавие)</span>}
                </span>
                <button onClick={e => { e.stopPropagation(); moveWidget(i, -1); }} className="text-yellow-400 hover:text-yellow-600">↑</button>
                <button onClick={e => { e.stopPropagation(); moveWidget(i, 1); }} className="text-yellow-400 hover:text-yellow-600">↓</button>
                <button onClick={e => { e.stopPropagation(); removeWidget(i); }} className="text-yellow-400 hover:text-red-500">×</button>
              </div>

              {isExpanded && (
                <div className="px-3 pb-3 space-y-2 border-t border-yellow-200 bg-yellow-50/50">
                  {/* ── latest_from_category settings ── */}
                  {wType === 'latest_from_category' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="Последен пост" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Брой постове</label>
                        <input type="number" min={1} max={10} value={(w.count as number) || 1} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 1 })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Съдържание</label>
                        <select value={(w.content_mode as string) || 'excerpt'} onChange={e => updateWidget(i, { content_mode: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs bg-white">
                          <option value="none">Без съдържание (само заглавие)</option>
                          <option value="excerpt">Excerpt (кратък текст)</option>
                          <option value="full">Цяло съдържание (всички блокове)</option>
                        </select>
                        <p className="text-[9px] text-gray-400 mt-0.5">
                          {(w.content_mode as string) === 'full'
                            ? 'Показва целия пост — всички блокове, изображения, текст и т.н.'
                            : (w.content_mode as string) === 'none'
                            ? 'Показва само заглавие, дата и категория.'
                            : 'Показва кратък текст. Ако няма ръчен excerpt, автоматично се извлича от първия текстов блок.'}
                        </p>
                      </div>
                      {((w.content_mode as string) || 'excerpt') === 'excerpt' && (
                        <div>
                          <label className="block text-[10px] font-medium text-gray-500 mb-1">Дължина на excerpt (символи)</label>
                          <input type="number" min={50} max={2000} step={50} value={(w.excerpt_length as number) || 200} onChange={e => updateWidget(i, { excerpt_length: parseInt(e.target.value) || 200 })}
                            className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                          <p className="text-[9px] text-gray-400 mt-0.5">Максимален брой символи. По-дълъг текст се отрязва с "..."</p>
                        </div>
                      )}
                      <div className="space-y-1">
                        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
                          <input type="checkbox" checked={(w.show_image as boolean) ?? true} onChange={e => updateWidget(i, { show_image: e.target.checked })}
                            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
                          Покажи featured image
                        </label>
                        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
                          <input type="checkbox" checked={(w.show_category as boolean) ?? true} onChange={e => updateWidget(i, { show_category: e.target.checked })}
                            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
                          Покажи категория
                        </label>
                        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
                          <input type="checkbox" checked={(w.show_date as boolean) ?? true} onChange={e => updateWidget(i, { show_date: e.target.checked })}
                            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
                          Покажи дата
                        </label>
                      </div>
                    </>
                  )}

                  {/* ── recent_posts settings ── */}
                  {wType === 'recent_posts' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="Последни постове" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Брой</label>
                        <input type="number" min={1} max={20} value={(w.count as number) || 5} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 5 })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div className="space-y-1">
                        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
                          <input type="checkbox" checked={(w.show_date as boolean) ?? true} onChange={e => updateWidget(i, { show_date: e.target.checked })}
                            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
                          Покажи дата
                        </label>
                        <label className="flex items-center gap-1.5 text-[10px] text-gray-500">
                          <input type="checkbox" checked={(w.show_category as boolean) ?? false} onChange={e => updateWidget(i, { show_category: e.target.checked })}
                            className="rounded border-gray-300 text-blue-600" style={{ width: 12, height: 12 }} />
                          Покажи категория
                        </label>
                      </div>
                    </>
                  )}

                  {/* ── popular_posts settings ── */}
                  {wType === 'popular_posts' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="Популярни постове" />
                      </div>
                      <CategorySelect
                        value={(w.category_id as string) || null}
                        includeChildren={(w.include_children as boolean) || false}
                        onChangeCategory={v => updateWidget(i, { category_id: v })}
                        onChangeChildren={v => updateWidget(i, { include_children: v })}
                      />
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Брой</label>
                        <input type="number" min={1} max={20} value={(w.count as number) || 5} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 5 })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                    </>
                  )}

                  {/* ── related_posts settings ── */}
                  {wType === 'related_posts' && (
                    <div>
                      <label className="block text-[10px] font-medium text-gray-500 mb-1">Брой</label>
                      <input type="number" min={1} max={10} value={(w.count as number) || 3} onChange={e => updateWidget(i, { count: parseInt(e.target.value) || 3 })}
                        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                    </div>
                  )}

                  {/* ── newsletter settings ── */}
                  {wType === 'newsletter' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Описание</label>
                        <input value={(w.description as string) || ''} onChange={e => updateWidget(i, { description: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                    </>
                  )}

                  {/* ── cta_banner settings ── */}
                  {wType === 'cta_banner' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Заглавие</label>
                        <input value={(w.title as string) || ''} onChange={e => updateWidget(i, { title: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Бутон текст</label>
                        <input value={(w.button_text as string) || ''} onChange={e => updateWidget(i, { button_text: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Бутон URL</label>
                        <input value={(w.button_url as string) || ''} onChange={e => updateWidget(i, { button_url: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                    </>
                  )}

                  {/* ── custom_html settings ── */}
                  {wType === 'custom_html' && (
                    <div>
                      <label className="block text-[10px] font-medium text-gray-500 mb-1">HTML код</label>
                      <textarea value={(w.html as string) || ''} onChange={e => updateWidget(i, { html: e.target.value })} rows={4}
                        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs font-mono" />
                    </div>
                  )}

                  {/* ── rich_text settings ── */}
                  {wType === 'rich_text' && (
                    <div>
                      <label className="block text-[10px] font-medium text-gray-500 mb-1">Съдържание (HTML)</label>
                      <textarea value={(w.content as string) || ''} onChange={e => updateWidget(i, { content: e.target.value })} rows={4}
                        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                    </div>
                  )}

                  {/* ── logo settings ── */}
                  {wType === 'logo' && (
                    <div>
                      <label className="block text-[10px] font-medium text-gray-500 mb-1">Logo URL</label>
                      <input value={(w.url as string) || ''} onChange={e => updateWidget(i, { url: e.target.value })}
                        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="https://..." />
                    </div>
                  )}

                  {/* ── image settings ── */}
                  {wType === 'image' && (
                    <>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Image URL</label>
                        <input value={(w.src as string) || ''} onChange={e => updateWidget(i, { src: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="https://..." />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Alt текст</label>
                        <input value={(w.alt as string) || ''} onChange={e => updateWidget(i, { alt: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Линк (optional)</label>
                        <input value={(w.link as string) || ''} onChange={e => updateWidget(i, { link: e.target.value })}
                          className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="https://..." />
                      </div>
                    </>
                  )}

                  {/* ── copyright settings ── */}
                  {wType === 'copyright' && (
                    <div>
                      <label className="block text-[10px] font-medium text-gray-500 mb-1">Текст</label>
                      <input value={(w.text as string) || ''} onChange={e => updateWidget(i, { text: e.target.value })}
                        className="w-full px-2 py-1.5 border border-gray-200 rounded text-xs" placeholder="© {{year}} {{site_name}}" />
                      <p className="text-[9px] text-gray-400 mt-0.5">Използвай {'{{year}}'} и {'{{site_name}}'} за автоматични стойности</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
      {showCatalog ? (
        <div className="border border-yellow-200 rounded-lg overflow-hidden">
          <div className="bg-yellow-50 px-3 py-2 flex items-center justify-between border-b border-yellow-200">
            <span className="text-xs font-medium text-yellow-800">Добави Widget</span>
            <button onClick={() => setShowCatalog(false)} className="text-xs text-yellow-600">Отказ</button>
          </div>
          <div className="max-h-60 overflow-y-auto p-1">
            {WIDGET_CATALOG.map(w => (
              <button key={w.type} onClick={() => addWidget(w.type)}
                className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-yellow-50 rounded text-xs">
                <span>{w.emoji}</span>
                <span className="font-medium text-gray-700">{w.label}</span>
                <span className="ml-auto text-gray-300 text-[10px]">{w.category}</span>
              </button>
            ))}
          </div>
        </div>
      ) : (
        <button onClick={() => setShowCatalog(true)}
          className="w-full px-3 py-2 text-xs font-medium text-yellow-700 border border-yellow-300 border-dashed rounded-lg hover:bg-yellow-50">
          + Добави Widget
        </button>
      )}
    </div>
  );
}
