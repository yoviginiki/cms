import { useState, useEffect, useRef, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, Loader2, Sun, Moon, Maximize2, ZoomIn, ZoomOut, RotateCcw } from 'lucide-react';
import { themeEngine } from '@/lib/api';

interface FrameDef { slug: string; title: string; description: string; }
interface SelectedElement {
  elementId: string;
  tokenPaths: string[];
  symbolicName: string;
  frameSlug: string;
}

const TOKEN_LABELS: Record<string, string> = {
  'semantic.color.brand': 'Brand Color',
  'semantic.color.text.heading': 'Heading Text',
  'semantic.color.text.body': 'Body Text',
  'semantic.color.text.muted': 'Muted Text',
  'semantic.color.text.link': 'Link Color',
  'semantic.color.text.inverse': 'Inverse Text',
  'semantic.color.background.canvas': 'Page Background',
  'semantic.color.background.surface': 'Surface Background',
  'semantic.color.background.raised': 'Raised Background',
  'semantic.color.background.inverse': 'Dark Background',
  'semantic.color.border.default': 'Border Color',
  'semantic.color.border.subtle': 'Subtle Border',
  'semantic.color.border.strong': 'Strong Border',
  'semantic.color.success': 'Success',
  'semantic.color.warning': 'Warning',
  'semantic.color.danger': 'Danger',
  'semantic.color.accent': 'Accent',
  'semantic.font.family.display': 'Display Font',
  'semantic.font.family.body': 'Body Font',
  'semantic.font.family.mono': 'Mono Font',
  'semantic.size.radius.sm': 'Small Radius',
  'semantic.size.radius.md': 'Medium Radius',
  'semantic.size.radius.lg': 'Large Radius',
  'semantic.size.radius.full': 'Full Radius',
  'semantic.shadow.sm': 'Small Shadow',
  'semantic.shadow.md': 'Medium Shadow',
  'semantic.shadow.lg': 'Large Shadow',
  'semantic.shadow.xl': 'XL Shadow',
  'semantic.font.size.sm': 'Small Text',
  'semantic.font.size.base': 'Base Text',
  'semantic.font.size.lg': 'Large Text',
  'semantic.font.size.xl': 'XL Text',
  'semantic.font.size.2xl': '2XL Text',
  'semantic.font.size.3xl': '3XL Text',
};

export default function ThemeStudio() {
  const { siteId = '', themeId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [mode, setMode] = useState('light');
  const [activeFrame, setActiveFrame] = useState('hero');
  const [zoom, setZoom] = useState(100);
  const [selected, setSelected] = useState<SelectedElement | null>(null);
  const [editDoc, setEditDoc] = useState<Record<string, unknown> | null>(null);
  const [isDirty, setIsDirty] = useState(false);
  const iframeRefs = useRef<Record<string, HTMLIFrameElement | null>>({});

  const { data: theme } = useQuery<any>({
    queryKey: ['theme-detail', siteId, themeId],
    queryFn: () => themeEngine.get(siteId, themeId).then((r: any) => r.data.data),
  });

  const { data: resolved } = useQuery<any>({
    queryKey: ['theme-resolved', siteId, mode],
    queryFn: () => themeEngine.resolve(siteId, mode).then((r: any) => r.data.data),
  });

  const { data: frames } = useQuery<FrameDef[]>({
    queryKey: ['studio-frames', siteId],
    queryFn: () => themeEngine.list(siteId).then(() =>
      // Use frames API
      fetch(`/api/v1/sites/${siteId}/theme-engine/studio/frames`, { credentials: 'include' })
        .then(r => r.json()).then(r => r.data)
    ),
  });

  useEffect(() => {
    if (theme?.document && !editDoc) setEditDoc(theme.document);
  }, [theme]);

  const saveMut = useMutation({
    mutationFn: (doc: Record<string, unknown>) => themeEngine.update(siteId, themeId, { document: doc }),
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['theme-detail', siteId, themeId] });
      queryClient.invalidateQueries({ queryKey: ['theme-resolved', siteId] });
    },
  });

  // Listen for messages from iframes
  useEffect(() => {
    const handler = (e: MessageEvent) => {
      if (!e.data?.type) return;
      if (e.data.type === 'click') {
        if (!e.data.element) { setSelected(null); return; }
        // Find which frame this came from
        let frameSlug = '';
        for (const [slug, iframe] of Object.entries(iframeRefs.current)) {
          if (iframe?.contentWindow === e.source) { frameSlug = slug; break; }
        }
        setSelected({ ...e.data.element, frameSlug });
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, []);

  // Broadcast token update to all iframes
  const updateToken = useCallback((path: string, value: string) => {
    Object.values(iframeRefs.current).forEach(iframe => {
      iframe?.contentWindow?.postMessage({ type: 'updateToken', path, value }, '*');
    });
  }, []);

  const updateTokenInDoc = useCallback((path: string, value: unknown) => {
    if (!editDoc) return;
    const updated = JSON.parse(JSON.stringify(editDoc));
    const keys = path.split('.');
    let current: any = updated;
    for (let i = 0; i < keys.length - 1; i++) {
      if (!current[keys[i]]) current[keys[i]] = {};
      current = current[keys[i]];
    }
    current[keys[keys.length - 1]] = value;
    setEditDoc(updated);
    setIsDirty(true);

    // Also broadcast to iframes for instant preview
    if (typeof value === 'object' && (value as any)?.$value) {
      const resolvedVal = String((value as any).$value);
      if (!resolvedVal.startsWith('{')) {
        updateToken(path, resolvedVal);
      }
    }
  }, [editDoc, updateToken]);

  const isSystem = theme?.is_system;
  const tokens = resolved?.tokens || {};
  const frameUrl = (slug: string) =>
    `/api/v1/sites/${siteId}/theme-engine/studio/frame/${slug}?theme_id=${themeId}&mode=${mode}&_t=${Date.now()}`;

  return (
    <div className="flex flex-col h-screen bg-neutral-900" data-theme="cms-admin">
      {/* Toolbar */}
      <div className="flex items-center justify-between h-11 px-3 bg-neutral-800 border-b border-neutral-700 shrink-0">
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/theme-engine/${themeId}`)}
            className="p-1 text-neutral-400 hover:text-white"><ArrowLeft size={16} /></button>
          <span className="text-sm font-medium text-white">{theme?.name || 'Studio'}</span>
          {isSystem && <span className="text-[10px] bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded">Read-only</span>}
          {isDirty && <span className="text-[10px] text-amber-400">Unsaved</span>}
        </div>

        <div className="flex items-center gap-3">
          {/* Frame picker */}
          <div className="flex bg-neutral-700 rounded-md p-0.5 gap-0.5">
            {(frames || []).map(f => (
              <button key={f.slug} onClick={() => setActiveFrame(f.slug)}
                className={`px-2 py-0.5 text-[11px] rounded ${activeFrame === f.slug ? 'bg-neutral-500 text-white' : 'text-neutral-400 hover:text-white'}`}>
                {f.title}
              </button>
            ))}
          </div>

          {/* Mode */}
          <div className="flex bg-neutral-700 rounded-md p-0.5">
            <button onClick={() => setMode('light')} className={`p-1 rounded ${mode === 'light' ? 'bg-neutral-500 text-white' : 'text-neutral-400'}`}>
              <Sun size={12} /></button>
            <button onClick={() => setMode('dark')} className={`p-1 rounded ${mode === 'dark' ? 'bg-neutral-500 text-white' : 'text-neutral-400'}`}>
              <Moon size={12} /></button>
          </div>

          {/* Zoom */}
          <div className="flex items-center gap-1">
            <button onClick={() => setZoom(z => Math.max(25, z - 25))} className="p-1 text-neutral-400 hover:text-white"><ZoomOut size={14} /></button>
            <span className="text-[11px] text-neutral-400 w-10 text-center">{zoom}%</span>
            <button onClick={() => setZoom(z => Math.min(200, z + 25))} className="p-1 text-neutral-400 hover:text-white"><ZoomIn size={14} /></button>
            <button onClick={() => setZoom(100)} className="p-1 text-neutral-400 hover:text-white" title="Reset"><RotateCcw size={12} /></button>
          </div>

          {!isSystem && (
            <button onClick={() => editDoc && saveMut.mutate(editDoc)} disabled={saveMut.isPending || !isDirty}
              className="flex items-center gap-1 px-3 py-1 text-xs bg-purple-600 text-white rounded hover:bg-purple-700 disabled:opacity-40">
              {saveMut.isPending ? <Loader2 size={12} className="animate-spin" /> : <Save size={12} />} Save
            </button>
          )}
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Center: Frame workspace */}
        <div className="flex-1 overflow-auto bg-neutral-900 flex items-center justify-center p-8">
          <div style={{ transform: `scale(${zoom / 100})`, transformOrigin: 'center center', transition: 'transform 0.2s' }}>
            <div className="bg-white rounded-lg shadow-2xl overflow-hidden" style={{ width: 900 }}>
              <iframe
                ref={el => { iframeRefs.current[activeFrame] = el; }}
                src={frameUrl(activeFrame)}
                className="w-full border-0"
                style={{ height: 600, display: 'block' }}
                title={`Frame: ${activeFrame}`}
              />
            </div>
          </div>
        </div>

        {/* Right: Inspector */}
        <div className="w-80 bg-neutral-800 border-l border-neutral-700 overflow-y-auto shrink-0">
          {selected ? (
            <div className="p-4 space-y-4">
              <div>
                <h3 className="text-sm font-semibold text-white">{selected.symbolicName.replace(/\./g, ' › ')}</h3>
                <p className="text-[10px] text-neutral-500 mt-0.5">Click element in the frame to inspect its tokens</p>
              </div>

              <div className="space-y-3">
                {selected.tokenPaths.map(path => {
                  const value = tokens[path];
                  const isColor = typeof value === 'string' && /^#[0-9a-f]{3,8}$/i.test(value);
                  const label = TOKEN_LABELS[path] || path.split('.').pop() || path;

                  return (
                    <div key={path} className="bg-neutral-700/50 rounded-lg p-3">
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-medium text-neutral-200">{label}</span>
                        {isColor && (
                          <span className="w-5 h-5 rounded border border-neutral-600" style={{ backgroundColor: value }} />
                        )}
                      </div>
                      <p className="text-[10px] text-neutral-500 font-mono mb-2">{path}</p>

                      {!isSystem && isColor && (
                        <div className="flex gap-1.5">
                          <input type="color" value={value || '#000000'}
                            onChange={e => {
                              updateToken(path, e.target.value);
                              updateTokenInDoc(path, { $type: 'color', $value: e.target.value });
                            }}
                            className="w-8 h-7 rounded border border-neutral-600 cursor-pointer" />
                          <input type="text" value={value || ''}
                            onChange={e => {
                              updateToken(path, e.target.value);
                              updateTokenInDoc(path, { $type: 'color', $value: e.target.value });
                            }}
                            className="flex-1 bg-neutral-700 text-neutral-200 text-xs font-mono px-2 py-1 rounded border border-neutral-600" />
                        </div>
                      )}

                      {!isSystem && !isColor && (
                        <input type="text" value={typeof value === 'string' ? value : JSON.stringify(value) || ''}
                          onChange={e => {
                            updateToken(path, e.target.value);
                            updateTokenInDoc(path, { $type: 'dimension', $value: e.target.value });
                          }}
                          className="w-full bg-neutral-700 text-neutral-200 text-xs font-mono px-2 py-1 rounded border border-neutral-600" />
                      )}

                      {isSystem && (
                        <p className="text-xs font-mono text-neutral-400">{typeof value === 'string' ? value : JSON.stringify(value)}</p>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center h-full text-neutral-500 p-6">
              <Maximize2 size={32} className="mb-3 opacity-30" />
              <p className="text-sm text-center">Click any element in the frame to inspect and edit its design tokens</p>
              <p className="text-[10px] text-neutral-600 mt-2">Colors, fonts, spacing, shadows — all editable live</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
