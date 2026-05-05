import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, Loader2, Download, Sun, Moon, ChevronRight, Info } from 'lucide-react';
import { themeEngine } from '@/lib/api';

// ═══════════════════════════════════════════
// Token descriptions — explains what each token does and where it's used
// ═══════════════════════════════════════════
const TOKEN_INFO: Record<string, { label: string; desc: string; usage: string }> = {
  // ─── Primitive Colors ───
  'primitive.color.primary':      { label: 'Primary Color', desc: 'The main brand color used across the site.', usage: 'Buttons, links, active states, focus rings, brand accents.' },
  'primitive.color.primaryDark':  { label: 'Primary Dark', desc: 'A darker shade of the primary color.', usage: 'Hover states on primary buttons, active links.' },
  'primitive.color.primaryLight': { label: 'Primary Light', desc: 'A lighter shade of the primary color.', usage: 'Hover backgrounds, subtle highlights, tag backgrounds.' },
  'primitive.color.secondary':    { label: 'Secondary Color', desc: 'Supporting color for less prominent elements.', usage: 'Secondary buttons, subtitles, metadata text, icons.' },
  'primitive.color.accent':       { label: 'Accent Color', desc: 'Eye-catching color for calls to action and highlights.', usage: 'CTA buttons, badges, notification dots, special highlights.' },
  'primitive.color.bg':           { label: 'Background', desc: 'The main page background color.', usage: 'Full page background (body), card backgrounds in dark mode.' },
  'primitive.color.bgAlt':        { label: 'Alt Background', desc: 'Alternative background for sections that need contrast.', usage: 'Alternating sections, sidebar background, code block background.' },
  'primitive.color.bgInverse':    { label: 'Inverse Background', desc: 'Dark background for inverted sections.', usage: 'Footer background, dark hero sections, tooltips.' },
  'primitive.color.text':         { label: 'Text Color', desc: 'The main text color for body content.', usage: 'Paragraphs, headings, list items — all readable text.' },
  'primitive.color.textMuted':    { label: 'Muted Text', desc: 'Softer text color for less important content.', usage: 'Dates, metadata, captions, placeholder text, helper text.' },
  'primitive.color.textInverse':  { label: 'Inverse Text', desc: 'Text color on dark backgrounds.', usage: 'Text inside dark sections, footer text, text on primary buttons.' },
  'primitive.color.border':       { label: 'Border Color', desc: 'Default border color for dividers and containers.', usage: 'Card borders, input borders, horizontal rules, table borders.' },
  'primitive.color.borderLight':  { label: 'Light Border', desc: 'Subtle border for separators.', usage: 'List item separators, subtle dividers between sections.' },
  'primitive.color.success':      { label: 'Success Color', desc: 'Green color indicating success or positive state.', usage: 'Success alerts, checkmarks, "published" badges, positive stats.' },
  'primitive.color.warning':      { label: 'Warning Color', desc: 'Yellow/amber color indicating caution.', usage: 'Warning alerts, "draft" badges, expiring notices.' },
  'primitive.color.danger':       { label: 'Danger Color', desc: 'Red color indicating errors or destructive actions.', usage: 'Error messages, delete buttons, validation errors, "failed" badges.' },
  'primitive.color.info':         { label: 'Info Color', desc: 'Blue color for informational elements.', usage: 'Info alerts, tooltips, help icons, link color in some contexts.' },

  // ─── Primitive Fonts ───
  'primitive.font.family.heading': { label: 'Heading Font', desc: 'Font family for all headings (h1-h6) and display text.', usage: 'Page titles, section headings, hero text, card titles, blog post titles.' },
  'primitive.font.family.body':    { label: 'Body Font', desc: 'Font family for all body text and paragraphs.', usage: 'Paragraphs, list items, form labels, navigation links, metadata.' },
  'primitive.font.family.mono':    { label: 'Mono Font', desc: 'Monospace font for code and technical content.', usage: 'Code blocks, inline code, terminal output, data tables.' },

  // ─── Primitive Sizes ───
  'primitive.size.1':  { label: 'Space 1 (4px)', desc: 'Smallest spacing unit.', usage: 'Icon margins, inline element gaps, tight padding.' },
  'primitive.size.2':  { label: 'Space 2 (8px)', desc: 'Small spacing.', usage: 'Button padding, input padding, list item gaps.' },
  'primitive.size.3':  { label: 'Space 3 (12px)', desc: 'Medium-small spacing.', usage: 'Card inner padding, form field spacing.' },
  'primitive.size.4':  { label: 'Space 4 (16px)', desc: 'Base spacing unit (1rem).', usage: 'Default padding, paragraph spacing, grid gaps.' },
  'primitive.size.6':  { label: 'Space 6 (24px)', desc: 'Medium spacing.', usage: 'Section padding, card spacing, larger gaps.' },
  'primitive.size.8':  { label: 'Space 8 (32px)', desc: 'Large spacing.', usage: 'Section margins, hero padding, large gaps.' },
  'primitive.size.12': { label: 'Space 12 (48px)', desc: 'Extra large spacing.', usage: 'Section separators, page section gaps.' },
  'primitive.size.16': { label: 'Space 16 (64px)', desc: 'Huge spacing.', usage: 'Page-level section dividers, hero top/bottom padding.' },

  // ─── Semantic Colors ───
  'semantic.color.brand':   { label: 'Brand Color', desc: 'The resolved brand color (references primary).', usage: 'Primary buttons, links, active nav items, brand-colored elements everywhere.' },
  'semantic.color.accent':  { label: 'Accent', desc: 'Resolved accent color.', usage: 'Call-to-action buttons, highlight badges, notification indicators.' },
  'semantic.color.success': { label: 'Success', desc: 'Resolved success color.', usage: 'Success toasts, "published" status badges, positive feedback.' },
  'semantic.color.warning': { label: 'Warning', desc: 'Resolved warning color.', usage: 'Warning banners, "unsaved changes" indicators, caution notices.' },
  'semantic.color.danger':  { label: 'Danger', desc: 'Resolved danger color.', usage: 'Delete confirmations, error messages, destructive action buttons.' },
  'semantic.color.info':    { label: 'Info', desc: 'Resolved info color.', usage: 'Informational alerts, help tooltips, documentation links.' },

  'semantic.color.background.canvas':  { label: 'Canvas BG', desc: 'The outermost page background.', usage: 'HTML body background, the color behind everything.' },
  'semantic.color.background.surface': { label: 'Surface BG', desc: 'Background for cards and elevated surfaces.', usage: 'Card backgrounds, sidebar background, dropdown menus, modal overlays.' },
  'semantic.color.background.raised':  { label: 'Raised BG', desc: 'Background for prominently elevated elements.', usage: 'Popover panels, sticky headers, floating action buttons.' },
  'semantic.color.background.inverse': { label: 'Inverse BG', desc: 'Dark background for inverted sections.', usage: 'Footer, dark hero sections, tooltip backgrounds.' },

  'semantic.color.text.body':    { label: 'Body Text', desc: 'Default text color for all body content.', usage: 'Every paragraph, list item, and general readable text on the site.' },
  'semantic.color.text.heading': { label: 'Heading Text', desc: 'Color for headings (h1-h6).', usage: 'All headings — page titles, section titles, card titles, blog titles.' },
  'semantic.color.text.muted':   { label: 'Muted Text', desc: 'De-emphasized text color.', usage: 'Dates, timestamps, captions, helper text, secondary info.' },
  'semantic.color.text.link':    { label: 'Link Text', desc: 'Color for clickable links.', usage: 'Inline links in text, navigation items, "read more" links.' },
  'semantic.color.text.inverse': { label: 'Inverse Text', desc: 'Text on dark backgrounds.', usage: 'Footer text, text inside dark hero sections, button text on primary bg.' },

  'semantic.color.border.subtle':  { label: 'Subtle Border', desc: 'Very light border for subtle separation.', usage: 'List separators, light dividers between sidebar items.' },
  'semantic.color.border.default': { label: 'Default Border', desc: 'Standard border color.', usage: 'Card borders, input field borders, table borders, dividers.' },
  'semantic.color.border.strong':  { label: 'Strong Border', desc: 'Prominent border for emphasis.', usage: 'Active input borders, selected card borders, emphasis dividers.' },

  // ─── Semantic Fonts ───
  'semantic.font.family.display': { label: 'Display Font', desc: 'Font for headings and display text (maps to heading primitive).', usage: 'h1-h6 tags, hero text, page titles, blog post titles — all headings.' },
  'semantic.font.family.body':    { label: 'Body Font', desc: 'Font for body text (maps to body primitive).', usage: 'All paragraphs, navigation, forms, buttons — everything that isn\'t a heading.' },
  'semantic.font.family.mono':    { label: 'Monospace Font', desc: 'Font for code (maps to mono primitive).', usage: 'Code blocks, inline <code> tags, terminal output, data displays.' },

  'semantic.font.size.sm':   { label: 'Small Text', desc: 'Small font size (0.875rem / 14px).', usage: 'Captions, metadata, helper text, fine print.' },
  'semantic.font.size.base': { label: 'Base Text', desc: 'Default font size (1rem / 16px).', usage: 'Body paragraphs, form inputs, navigation links.' },
  'semantic.font.size.lg':   { label: 'Large Text', desc: 'Slightly larger text (1.125rem / 18px).', usage: 'Lead paragraphs, emphasized body text, card descriptions.' },
  'semantic.font.size.xl':   { label: 'XL Text', desc: 'Large text (1.25rem / 20px).', usage: 'Subheadings, card titles, section intros.' },
  'semantic.font.size.2xl':  { label: '2XL Text', desc: 'Heading size (1.5rem / 24px).', usage: 'h3 headings, widget titles, sidebar section headers.' },
  'semantic.font.size.3xl':  { label: '3XL Text', desc: 'Large heading (1.875rem / 30px).', usage: 'h2 headings, page section titles, blog post titles.' },

  // ─── Semantic Sizes ───
  'semantic.size.radius.sm':   { label: 'Small Radius', desc: 'Slight rounding for small elements.', usage: 'Tags, badges, small buttons, input fields.' },
  'semantic.size.radius.md':   { label: 'Medium Radius', desc: 'Standard rounding for cards and buttons.', usage: 'Cards, buttons, dropdowns, modals, alerts.' },
  'semantic.size.radius.lg':   { label: 'Large Radius', desc: 'More rounding for prominent elements.', usage: 'Large cards, hero sections, featured images.' },
  'semantic.size.radius.full': { label: 'Full Radius', desc: 'Fully rounded (pill shape / circle).', usage: 'Avatar images, pill badges, round buttons, toggle switches.' },

  // ─── Shadows ───
  'semantic.shadow.sm': { label: 'Small Shadow', desc: 'Subtle shadow for slight elevation.', usage: 'Buttons, inputs on focus, small cards.' },
  'semantic.shadow.md': { label: 'Medium Shadow', desc: 'Standard elevation shadow.', usage: 'Cards, dropdowns, floating elements.' },
  'semantic.shadow.lg': { label: 'Large Shadow', desc: 'Prominent shadow for elevated content.', usage: 'Modals, popovers, sticky headers.' },
  'semantic.shadow.xl': { label: 'XL Shadow', desc: 'Maximum elevation shadow.', usage: 'Full-page modals, lightboxes, dragged elements.' },
};

function getTokenInfo(path: string) {
  if (TOKEN_INFO[path]) return TOKEN_INFO[path];
  // Generate from path
  const parts = path.split('.');
  const name = parts[parts.length - 1];
  return { label: name, desc: '', usage: '' };
}

// ═══════════════════════════════════════════

interface TokenNode {
  path: string;
  name: string;
  type?: string;
  value?: unknown;
  description?: string;
  children?: TokenNode[];
  isGroup: boolean;
}

export default function ThemeEditor() {
  const { siteId = '', themeId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [mode, setMode] = useState('light');
  const [selectedPath, setSelectedPath] = useState<string | null>(null);
  const [editDoc, setEditDoc] = useState<Record<string, unknown> | null>(null);
  const [isDirty, setIsDirty] = useState(false);

  const { data: theme, isLoading } = useQuery<any>({
    queryKey: ['theme-detail', siteId, themeId],
    queryFn: () => themeEngine.get(siteId, themeId).then((r: any) => r.data.data),
  });

  const { data: resolved } = useQuery<any>({
    queryKey: ['theme-resolved', siteId, mode],
    queryFn: () => themeEngine.resolve(siteId, mode).then((r: any) => r.data.data),
  });

  useEffect(() => {
    if (theme?.document && !editDoc) setEditDoc(theme.document);
  }, [theme]);

  const saveMut = useMutation({
    mutationFn: (doc: Record<string, unknown>) =>
      themeEngine.update(siteId, themeId, { document: doc }),
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['theme-detail', siteId, themeId] });
      queryClient.invalidateQueries({ queryKey: ['theme-resolved', siteId] });
    },
  });

  const isSystem = theme?.is_system;
  const tokens = resolved?.tokens || {};
  const tree = buildTree(editDoc || theme?.document || {});

  if (isLoading) {
    return <div className="flex items-center justify-center h-screen"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  }

  return (
    <div className="flex flex-col h-screen bg-gray-50" data-theme="cms-admin">
      {/* Toolbar */}
      <div className="flex items-center justify-between h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/theme-engine`)} className="btn btn-ghost btn-xs btn-square">
            <ArrowLeft size={16} />
          </button>
          <div className="flex bg-base-200 rounded-md p-0.5">
            <span className="px-2.5 py-1 rounded text-xs font-medium bg-base-100 shadow-sm">Editor</span>
            <button onClick={() => navigate(`/sites/${siteId}/theme-engine/${themeId}/studio`)}
              className="px-2.5 py-1 rounded text-xs font-medium text-base-content/40 hover:text-base-content/70">Studio</button>
          </div>
          <h1 className="text-sm font-semibold">{theme?.name || 'Theme'}</h1>
          {isSystem && <span className="badge badge-sm badge-info badge-outline text-[10px]">System (read-only)</span>}
          {isDirty && <span className="text-[10px] text-warning font-medium">Unsaved</span>}
        </div>
        <div className="flex items-center gap-2">
          <div className="flex bg-base-200 rounded-lg p-0.5">
            <button onClick={() => setMode('light')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium ${mode === 'light' ? 'bg-base-100 shadow-sm' : 'text-base-content/40'}`}>
              <Sun size={12} /> Light
            </button>
            <button onClick={() => setMode('dark')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium ${mode === 'dark' ? 'bg-base-100 shadow-sm' : 'text-base-content/40'}`}>
              <Moon size={12} /> Dark
            </button>
          </div>
          <button onClick={() => {
            const json = JSON.stringify(editDoc || theme?.document, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
            a.download = `${theme?.slug || 'theme'}-tokens.json`; a.click();
          }} className="btn btn-ghost btn-sm text-xs gap-1">
            <Download size={12} /> Export
          </button>
          {!isSystem && (
            <button onClick={() => editDoc && saveMut.mutate(editDoc)}
              disabled={saveMut.isPending || !isDirty}
              className="btn btn-primary btn-sm text-xs gap-1">
              {saveMut.isPending ? <Loader2 size={12} className="animate-spin" /> : <Save size={12} />}
              Save
            </button>
          )}
        </div>
      </div>

      {/* Two-pane: Token List + Inspector */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left: All tokens as a flat editable list grouped by category */}
        <div className="flex-1 overflow-y-auto p-4">
          <div className="max-w-3xl mx-auto space-y-6">
            {tree.map(tier => (
              <TierSection key={tier.path} tier={tier} tokens={tokens} selectedPath={selectedPath}
                onSelect={setSelectedPath} isSystem={isSystem}
                onEditValue={(path, value) => {
                  if (!editDoc) return;
                  const updated = JSON.parse(JSON.stringify(editDoc));
                  setTokenValue(updated, path, value);
                  setEditDoc(updated);
                  setIsDirty(true);
                }} />
            ))}

            <p className="text-xs text-base-content/20 text-center py-4">
              {resolved?.count || 0} tokens resolved &middot; hash: {resolved?.hash?.slice(0, 8)}
            </p>
          </div>
        </div>

        {/* Right: Inspector for selected token */}
        <div className="w-96 bg-base-100 border-l border-base-300/30 overflow-y-auto shrink-0">
          {selectedPath ? (
            <TokenDetailPanel path={selectedPath} tokens={tokens}
              rawToken={findToken(editDoc || {}, selectedPath)}
              isSystem={isSystem}
              onUpdate={(value) => {
                if (!editDoc) return;
                const updated = JSON.parse(JSON.stringify(editDoc));
                setTokenValue(updated, selectedPath, value);
                setEditDoc(updated);
                setIsDirty(true);
              }} />
          ) : (
            <div className="flex flex-col items-center justify-center h-full text-base-content/20 p-6">
              <Info size={32} className="mb-3" />
              <p className="text-sm text-center">Click any token to see details and edit its value</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Tier Section (primitive / semantic / component) ───
function TierSection({ tier, tokens, selectedPath, onSelect, isSystem, onEditValue }: any) {
  const tierLabels: Record<string, string> = {
    primitive: '🎨 Primitives — Raw values (colors, fonts, sizes)',
    semantic: '🏷️ Semantics — What each value means (brand, background, text...)',
    component: '🧩 Components — Block-specific tokens',
  };

  return (
    <div>
      <h2 className="text-xs font-bold text-base-content/40 uppercase tracking-wider mb-3">
        {tierLabels[tier.name] || tier.name}
      </h2>
      <div className="bg-base-100 rounded-xl border border-base-300/30 divide-y divide-base-300/20">
        {tier.children?.map((group: any) => (
          <GroupSection key={group.path} group={group} tokens={tokens} selectedPath={selectedPath}
            onSelect={onSelect} isSystem={isSystem} onEditValue={onEditValue} depth={0} />
        ))}
      </div>
    </div>
  );
}

function GroupSection({ group, tokens, selectedPath, onSelect, isSystem, onEditValue, depth }: any) {
  const [expanded, setExpanded] = useState(true);

  if (!group.isGroup) {
    return <TokenRow path={group.path} tokens={tokens} selectedPath={selectedPath}
      onSelect={onSelect} isSystem={isSystem} onEditValue={onEditValue} />;
  }

  return (
    <div>
      <button onClick={() => setExpanded(!expanded)}
        className="flex items-center gap-2 w-full px-4 py-2 text-left hover:bg-base-200/50"
        style={{ paddingLeft: 16 + depth * 16 }}>
        <ChevronRight size={12} className={`text-base-content/30 transition-transform ${expanded ? 'rotate-90' : ''}`} />
        <span className="text-xs font-semibold text-base-content/60 uppercase tracking-wider">{group.name}</span>
        <span className="text-[10px] text-base-content/20">{group.children?.length || 0}</span>
      </button>
      {expanded && group.children?.map((child: any) =>
        child.isGroup ? (
          <GroupSection key={child.path} group={child} tokens={tokens} selectedPath={selectedPath}
            onSelect={onSelect} isSystem={isSystem} onEditValue={onEditValue} depth={depth + 1} />
        ) : (
          <TokenRow key={child.path} path={child.path} tokens={tokens} selectedPath={selectedPath}
            onSelect={onSelect} isSystem={isSystem} onEditValue={onEditValue} indent={depth + 1} />
        )
      )}
    </div>
  );
}

function TokenRow({ path, tokens, selectedPath, onSelect, isSystem, onEditValue, indent = 0 }: any) {
  const resolvedValue = tokens[path];
  const info = getTokenInfo(path);
  const isColor = path.includes('color') || (typeof resolvedValue === 'string' && /^#[0-9a-f]{3,8}$/i.test(resolvedValue));
  const isFont = path.includes('font.family');
  const isSelected = selectedPath === path;

  return (
    <div onClick={() => onSelect(path)}
      className={`flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors ${
        isSelected ? 'bg-primary/5 border-l-2 border-primary' : 'hover:bg-base-200/50 border-l-2 border-transparent'
      }`}
      style={{ paddingLeft: 20 + indent * 16 }}>

      {/* Color swatch */}
      {isColor && (
        <div className="relative">
          <span className="block w-8 h-8 rounded-lg border border-base-300/30 shadow-sm"
            style={{ backgroundColor: String(resolvedValue || '#ccc') }} />
          {!isSystem && (
            <input type="color" value={String(resolvedValue || '#000000')}
              onChange={e => onEditValue(path, { $type: 'color', $value: e.target.value })}
              className="absolute inset-0 w-8 h-8 opacity-0 cursor-pointer"
              onClick={e => e.stopPropagation()} />
          )}
        </div>
      )}

      {/* Font preview */}
      {isFont && !isColor && (
        <span className="text-sm w-8 text-center" style={{ fontFamily: String(resolvedValue || 'inherit') }}>Aa</span>
      )}

      {/* No swatch for non-color/font */}
      {!isColor && !isFont && (
        <span className="w-8 h-8 rounded-lg bg-base-200/50 flex items-center justify-center text-[10px] text-base-content/30 font-mono">
          {typeof resolvedValue === 'string' && resolvedValue.length < 8 ? resolvedValue : '···'}
        </span>
      )}

      {/* Label + description */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium text-base-content/80">{info.label}</span>
          {info.usage && (
            <span className="text-[10px] text-base-content/25 truncate max-w-[200px]">{info.usage.split(',')[0]}</span>
          )}
        </div>
        <span className="text-[10px] text-base-content/30 font-mono">{path}</span>
      </div>

      {/* Resolved value */}
      <span className="text-xs font-mono text-base-content/40 truncate max-w-[140px]">
        {isFont ? 'Font' : (typeof resolvedValue === 'string' ? resolvedValue : '—')}
      </span>
    </div>
  );
}

// ─── Detail Panel (right side) ───
function TokenDetailPanel({ path, tokens, rawToken, isSystem, onUpdate }: any) {
  const info = getTokenInfo(path);
  const resolvedValue = tokens[path];
  const rawValue = rawToken?.$value ?? rawToken;
  const isRef = typeof rawValue === 'string' && rawValue.startsWith('{');
  const isColor = path.includes('color') || (typeof resolvedValue === 'string' && /^#[0-9a-f]{3,8}$/i.test(String(resolvedValue)));
  const isFont = path.includes('font.family');
  const isShadow = path.includes('shadow');
  const isSize = path.includes('size') || path.includes('radius');

  return (
    <div className="p-5 space-y-5">
      {/* Header */}
      <div>
        <h3 className="text-lg font-bold text-base-content/90">{info.label}</h3>
        <p className="text-[11px] text-base-content/30 font-mono mt-0.5">{path}</p>
        {rawToken?.$type && (
          <span className="inline-block mt-1.5 badge badge-sm badge-ghost text-[10px]">{rawToken.$type}</span>
        )}
      </div>

      {/* Description */}
      {info.desc && (
        <div className="bg-base-200/50 rounded-lg p-3">
          <p className="text-sm text-base-content/70 leading-relaxed">{info.desc}</p>
        </div>
      )}

      {/* Where it's used */}
      {info.usage && (
        <div>
          <h4 className="text-[10px] font-semibold text-base-content/40 uppercase tracking-wider mb-1">Where it's used</h4>
          <p className="text-sm text-base-content/60">{info.usage}</p>
        </div>
      )}

      {/* Resolved value preview */}
      <div>
        <h4 className="text-[10px] font-semibold text-base-content/40 uppercase tracking-wider mb-2">Current Value</h4>
        {isColor && (
          <div className="flex items-center gap-3 mb-2">
            <span className="w-16 h-16 rounded-xl border-2 border-base-300/30 shadow-md"
              style={{ backgroundColor: String(resolvedValue) }} />
            <div>
              <p className="text-lg font-mono font-bold text-base-content/80">{String(resolvedValue)}</p>
              {isRef && <p className="text-[10px] text-primary font-mono">→ {rawValue}</p>}
            </div>
          </div>
        )}
        {isFont && (
          <div className="bg-base-200/30 rounded-lg p-4 mb-2">
            <p className="text-2xl mb-1" style={{ fontFamily: String(resolvedValue) }}>
              The quick brown fox jumps
            </p>
            <p className="text-sm" style={{ fontFamily: String(resolvedValue) }}>
              ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789
            </p>
            <p className="text-xs text-base-content/40 mt-2 font-mono">{String(resolvedValue)}</p>
          </div>
        )}
        {isShadow && (
          <div className="flex items-center justify-center p-6">
            <div className="w-32 h-20 bg-base-100 rounded-lg"
              style={{ boxShadow: String(resolvedValue) }} />
          </div>
        )}
        {!isColor && !isFont && !isShadow && (
          <p className="text-base font-mono text-base-content/70 bg-base-200/30 rounded-lg px-3 py-2">
            {Array.isArray(resolvedValue) ? resolvedValue.join(', ') : String(resolvedValue ?? '—')}
          </p>
        )}
      </div>

      {/* Source */}
      <div>
        <h4 className="text-[10px] font-semibold text-base-content/40 uppercase tracking-wider mb-1">Source</h4>
        <p className="text-xs font-mono text-base-content/50">
          {isRef ? (
            <span><span className="text-primary">{rawValue}</span> (reference)</span>
          ) : (
            <span>{typeof rawValue === 'string' ? rawValue : JSON.stringify(rawValue)} (literal)</span>
          )}
        </p>
      </div>

      {/* Edit */}
      {!isSystem && (
        <div className="border-t border-base-300/20 pt-4">
          <h4 className="text-[10px] font-semibold text-base-content/40 uppercase tracking-wider mb-2">Edit</h4>

          {isColor && (
            <div className="space-y-2">
              <div className="flex gap-2">
                <input type="color" value={String(resolvedValue || '#000000')}
                  onChange={e => onUpdate({ $type: 'color', $value: e.target.value })}
                  className="w-12 h-10 rounded-lg border border-base-300/30 cursor-pointer" />
                <input type="text" value={String(rawValue || '')}
                  onChange={e => onUpdate({ $type: 'color', $value: e.target.value })}
                  className="input input-bordered input-sm flex-1 font-mono text-xs"
                  placeholder="#hex or {reference.path}" />
              </div>
            </div>
          )}

          {isFont && (
            <input type="text" value={typeof rawValue === 'string' ? rawValue : ''}
              onChange={e => onUpdate({ $type: 'fontFamily', $value: e.target.value })}
              className="input input-bordered input-sm w-full font-mono text-xs"
              placeholder="Font family or {reference.path}" />
          )}

          {(isSize || isShadow) && (
            <input type="text" value={typeof rawValue === 'string' ? rawValue : ''}
              onChange={e => onUpdate({ $type: rawToken?.$type || 'dimension', $value: e.target.value })}
              className="input input-bordered input-sm w-full font-mono text-xs"
              placeholder="Value or {reference.path}" />
          )}

          {!isColor && !isFont && !isSize && !isShadow && (
            <input type="text"
              value={typeof rawValue === 'string' ? rawValue : JSON.stringify(rawValue)}
              onChange={e => onUpdate({ $type: rawToken?.$type || 'string', $value: e.target.value })}
              className="input input-bordered input-sm w-full font-mono text-xs"
              placeholder="Value or {reference.path}" />
          )}

          <p className="text-[10px] text-base-content/25 mt-2">
            Use <code className="bg-base-200 px-1 rounded">{'{'}path.to.token{'}'}</code> to reference another token
          </p>
        </div>
      )}
    </div>
  );
}

// ─── Utility functions ───
function buildTree(doc: Record<string, unknown>, prefix = ''): TokenNode[] {
  const nodes: TokenNode[] = [];
  for (const [key, value] of Object.entries(doc)) {
    if (key.startsWith('$')) continue;
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      const obj = value as Record<string, unknown>;
      if ('$value' in obj) {
        nodes.push({ path, name: key, type: obj.$type as string, value: obj.$value, isGroup: false });
      } else {
        const children = buildTree(obj, path);
        if (children.length > 0) {
          nodes.push({ path, name: key, isGroup: true, children });
        }
      }
    }
  }
  return nodes;
}

function findToken(doc: Record<string, unknown>, path: string): any {
  const keys = path.split('.');
  let current: any = doc;
  for (const key of keys) {
    if (!current || typeof current !== 'object') return null;
    current = current[key];
  }
  return current;
}

function setTokenValue(doc: Record<string, unknown>, path: string, value: unknown): void {
  const keys = path.split('.');
  let current: any = doc;
  for (let i = 0; i < keys.length - 1; i++) {
    if (!current[keys[i]]) current[keys[i]] = {};
    current = current[keys[i]];
  }
  current[keys[keys.length - 1]] = value;
}
