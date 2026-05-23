import { useState, useRef, useEffect, useMemo } from 'react';
import { Search, Check, ChevronDown, X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { customFonts as customFontsApi } from '@/lib/api';
import {
  ALL_FONTS, FONT_CATEGORIES, SYSTEM_FONT_STACK,
  buildGoogleFontUrl, buildFontStack, findFont,
  type GoogleFont, type FontCategory,
} from '@/lib/googleFonts';

interface FontPickerProps {
  label: string;
  value: string;              // Current font-family value (e.g., "'Inter', system-ui, sans-serif")
  onChange: (value: string) => void;
  showWeights?: boolean;      // Show weight checkboxes
  selectedWeights?: number[]; // Currently selected weights
  onWeightsChange?: (weights: number[]) => void;
}

// Track which fonts have been loaded into the page for preview
const loadedFonts = new Set<string>();

function loadFontPreview(family: string) {
  if (family === 'System Default' || loadedFonts.has(family)) return;
  loadedFonts.add(family);
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = buildGoogleFontUrl(family, [400, 700]);
  document.head.appendChild(link);
}

export function FontPicker({ label, value, onChange, showWeights, selectedWeights, onWeightsChange }: FontPickerProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState<FontCategory | 'all'>('all');
  const containerRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const { siteId } = useParams<{ siteId: string }>();

  // Load custom fonts for this site
  const { data: siteCustomFonts } = useQuery<any[]>({
    queryKey: ['custom-fonts', siteId],
    queryFn: () => siteId ? customFontsApi.list(siteId).then((r: any) => r.data.data) : Promise.resolve([]),
    enabled: !!siteId,
  });

  // Unique custom font families
  const customFamilies = useMemo(() => {
    const families = new Set<string>();
    (siteCustomFonts || []).forEach((f: any) => families.add(f.family));
    return Array.from(families);
  }, [siteCustomFonts]);

  // Resolve current font from value
  const currentFont = findFont(value);
  const displayName = currentFont?.family || (value ? value.split(',')[0].replace(/['"]/g, '').trim() : 'System Default');

  // Filter fonts — custom fonts at top
  const filtered = useMemo(() => {
    let list = ALL_FONTS;
    if (category !== 'all') list = list.filter(f => f.category === category);
    if (search) {
      const q = search.toLowerCase();
      list = list.filter(f => f.family.toLowerCase().includes(q));
    }
    return [...list].sort((a, b) => {
      if (a.popular && !b.popular) return -1;
      if (!a.popular && b.popular) return 1;
      return a.family.localeCompare(b.family);
    });
  }, [search, category]);

  // Load preview fonts for visible items (lazy)
  useEffect(() => {
    if (!isOpen) return;
    filtered.slice(0, 15).forEach(f => loadFontPreview(f.family));
  }, [isOpen, filtered]);

  // Close on outside click
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) setIsOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [isOpen]);

  // Focus search on open
  useEffect(() => {
    if (isOpen) searchRef.current?.focus();
  }, [isOpen]);

  const selectFont = (font: GoogleFont) => {
    if (font.family === 'System Default') {
      onChange(SYSTEM_FONT_STACK);
    } else {
      onChange(buildFontStack(font.family, font.category));
    }
    loadFontPreview(font.family);
    setIsOpen(false);
    setSearch('');
  };

  // Load current font for preview display
  useEffect(() => { if (currentFont) loadFontPreview(currentFont.family); }, [currentFont]);

  return (
    <div ref={containerRef} className="relative">
      <label className="text-[10px] text-base-content/40 mb-1 block">{label}</label>

      {/* Selected font display / trigger */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center justify-between w-full px-2.5 py-1.5 bg-base-200/50 border border-base-300/30 rounded-lg text-left hover:bg-base-200/80 transition-colors"
      >
        <div className="flex items-center gap-2 min-w-0">
          <span className="text-base font-medium text-base-content/80 truncate"
            style={{ fontFamily: currentFont ? `'${currentFont.family}', ${currentFont.category}` : value || 'system-ui' }}>
            Aa
          </span>
          <span className="text-[11px] text-base-content/70 truncate">{displayName}</span>
        </div>
        <ChevronDown size={12} className={`text-base-content/30 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown — fixed position to escape overflow:auto parents */}
      {isOpen && (() => {
        const rect = containerRef.current?.getBoundingClientRect();
        const spaceBelow = rect ? window.innerHeight - rect.bottom - 12 : 380;
        const spaceAbove = rect ? rect.top - 12 : 0;
        const openAbove = spaceBelow < 200 && spaceAbove > spaceBelow;
        const maxH = Math.max(120, Math.min(380, openAbove ? spaceAbove : spaceBelow));
        const dropdownStyle: React.CSSProperties = rect ? {
          position: 'fixed',
          top: openAbove ? rect.top - maxH - 4 : rect.bottom + 4,
          left: rect.left,
          width: rect.width,
          maxHeight: maxH,
          zIndex: 9999,
        } : { position: 'absolute', left: 0, right: 0, marginTop: 4, maxHeight: 380, zIndex: 50 };

        return (
        <div className="bg-base-100 border border-base-300/30 rounded-xl shadow-xl overflow-hidden"
          style={dropdownStyle}>

          {/* Search */}
          <div className="p-2 border-b border-base-300/20">
            <div className="relative">
              <Search size={12} className="absolute left-2 top-1/2 -translate-y-1/2 text-base-content/30" />
              <input
                ref={searchRef}
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Search fonts..."
                className="w-full pl-7 pr-7 py-1.5 text-[11px] bg-base-200/50 border border-base-300/20 rounded-md focus:outline-none focus:border-primary/30"
              />
              {search && (
                <button onClick={() => setSearch('')} className="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/30">
                  <X size={11} />
                </button>
              )}
            </div>
          </div>

          {/* Category filters */}
          <div className="flex gap-0.5 p-1.5 border-b border-base-300/20">
            {FONT_CATEGORIES.map(cat => (
              <button key={cat.value} onClick={() => setCategory(cat.value)}
                className={`px-2 py-0.5 text-[10px] rounded-md font-medium transition-colors ${
                  category === cat.value ? 'bg-primary/10 text-primary' : 'text-base-content/40 hover:text-base-content/60'
                }`}>
                {cat.label}
              </button>
            ))}
          </div>

          {/* Font list */}
          <div className="overflow-y-auto" style={{ maxHeight: 260 }}
            onScroll={e => {
              // Lazy load fonts as user scrolls
              const el = e.target as HTMLElement;
              const visibleIdx = Math.floor(el.scrollTop / 36) + 15;
              const newFonts = filtered.slice(0, visibleIdx).map(f => f.family);
              newFonts.forEach(loadFontPreview);
            }}>
            {/* Custom uploaded fonts — always at top */}
            {customFamilies.length > 0 && !search && (
              <>
                <div className="px-3 py-1 text-[9px] text-primary font-semibold uppercase tracking-wider bg-primary/5 sticky top-0">
                  Custom Fonts
                </div>
                {customFamilies.map(family => {
                  const sel = displayName === family;
                  return (
                    <button key={`custom-${family}`}
                      onClick={() => onChange(`'${family}', sans-serif`)}
                      className={`flex items-center gap-3 w-full px-3 py-2 text-left hover:bg-base-200/50 ${sel ? 'bg-primary/5' : ''}`}>
                      <span className="text-lg w-8 text-center" style={{ fontFamily: `'${family}', sans-serif` }}>Aa</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-1.5">
                          <span className="text-[11px] font-medium truncate">{family}</span>
                          <span className="text-[8px] bg-success/10 text-success px-1 rounded">Custom</span>
                        </div>
                      </div>
                      {sel && <Check size={14} className="text-primary shrink-0" />}
                    </button>
                  );
                })}
                <div className="border-b border-base-300/20 my-1" />
              </>
            )}
            {filtered.length === 0 && customFamilies.length === 0 && (
              <div className="p-4 text-center text-[11px] text-base-content/30">No fonts found</div>
            )}
            {filtered.map(font => {
              const isSelected = currentFont?.family === font.family ||
                (font.family === 'System Default' && !currentFont);
              return (
                <button
                  key={font.family}
                  onClick={() => selectFont(font)}
                  className={`flex items-center gap-3 w-full px-3 py-2 text-left hover:bg-base-200/50 transition-colors ${
                    isSelected ? 'bg-primary/5' : ''
                  }`}
                >
                  {/* Font preview */}
                  <span className="text-lg w-8 text-center text-base-content/60"
                    style={{ fontFamily: font.family === 'System Default' ? 'system-ui' : `'${font.family}', ${font.category}` }}>
                    Aa
                  </span>

                  {/* Font name + meta */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-1.5">
                      <span className="text-[11px] font-medium text-base-content/80 truncate">{font.family}</span>
                      {font.popular && <span className="text-[8px] bg-primary/10 text-primary px-1 rounded">Popular</span>}
                      {font.variable && <span className="text-[8px] bg-base-300/50 text-base-content/30 px-1 rounded">Variable</span>}
                    </div>
                    <span className="text-[9px] text-base-content/25">
                      {font.category} · {font.weights.length} weight{font.weights.length !== 1 ? 's' : ''}
                    </span>
                  </div>

                  {/* Selected indicator */}
                  {isSelected && <Check size={13} className="text-primary shrink-0" />}
                </button>
              );
            })}
          </div>
        </div>
        );
      })()}

      {/* Weight selector (optional) */}
      {showWeights && currentFont && currentFont.weights.length > 1 && (
        <div className="mt-2">
          <label className="text-[9px] text-base-content/30 uppercase tracking-wider mb-1 block">Weights to load</label>
          <div className="flex flex-wrap gap-1">
            {currentFont.weights.map(w => {
              const active = selectedWeights?.includes(w) ?? (w === 400 || w === 700);
              return (
                <button key={w} onClick={() => {
                  if (!onWeightsChange) return;
                  const current = selectedWeights ?? [400, 700];
                  onWeightsChange(active ? current.filter(x => x !== w) : [...current, w].sort((a, b) => a - b));
                }}
                  className={`px-1.5 py-0.5 text-[10px] rounded border transition-colors ${
                    active ? 'bg-primary/10 border-primary/30 text-primary' : 'border-base-300/30 text-base-content/30'
                  }`}
                  style={{ fontFamily: currentFont ? `'${currentFont.family}', ${currentFont.category}` : undefined, fontWeight: w }}
                >
                  {w}
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
