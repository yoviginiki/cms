import { useState, useRef, useEffect, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { Palette, Copy, Loader2, Check, Upload, Eye, Power, Trash2, Type } from 'lucide-react';
import { themeEngine, customFonts } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface ThemeItem {
  id: string;
  name: string;
  slug: string;
  description?: string;
  modes?: string[];
  is_system: boolean;
  is_assigned: boolean;
  parent_theme_id?: string;
}

export default function ThemeEngine() {
  const { siteId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [importJson, setImportJson] = useState('');
  const [showImport, setShowImport] = useState(false);
  const { toast } = useToast();

  const { data: themes, isLoading, error } = useQuery<ThemeItem[]>({
    queryKey: ['theme-engine', siteId],
    queryFn: () => themeEngine.list(siteId).then((r: any) => {
      const d = r.data?.data;
      return Array.isArray(d) ? d : [];
    }),
  });

  const forkMut = useMutation({
    mutationFn: ({ themeId, name }: { themeId: string; name: string }) =>
      themeEngine.fork(siteId, themeId, name),
    onSuccess: (r: any) => {
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
      navigate(`/sites/${siteId}/theme-engine/${r.data.data.id}`);
    },
  });

  const activateMut = useMutation({
    mutationFn: (themeId: string) => themeEngine.assign(siteId, themeId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] }),
  });

  const importMut = useMutation({
    mutationFn: (doc: Record<string, unknown>) => themeEngine.importTheme(siteId, { document: doc }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
      setShowImport(false);
      setImportJson('');
    },
  });

  const handleFork = (theme: ThemeItem) => {
    const name = prompt('Name for the forked theme:', theme.name + ' (Custom)');
    if (name) forkMut.mutate({ themeId: theme.id, name });
  };

  const handleImport = () => {
    try {
      const doc = JSON.parse(importJson);
      importMut.mutate(doc);
    } catch {
      toast({ type: 'error', message: 'Invalid JSON format' });
    }
  };

  const systemThemes = themes?.filter(t => t.is_system) || [];
  const customThemes = themes?.filter(t => !t.is_system) || [];

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-base-content flex items-center gap-2">
            <Palette className="h-6 w-6 text-purple-500" /> Theme Engine
          </h1>
          <p className="mt-1 text-sm text-base-content/50">W3C Design Tokens-based theme system</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => setShowImport(true)}
            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-base-300 text-base-content/70 hover:bg-base-200">
            <Upload className="h-3.5 w-3.5" /> Import JSON
          </button>
        </div>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-base-content/40" />
        </div>
      )}

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-700">
          Failed to load themes: {(error as any)?.message || 'Unknown error'}
        </div>
      )}

      {/* System Themes */}
      {systemThemes.length > 0 && (
        <div className="mb-8">
          <h2 className="text-sm font-semibold text-base-content/50 uppercase tracking-wider mb-3">System Themes</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {systemThemes.map(theme => (
              <ThemeCard key={theme.id} theme={theme} siteId={siteId}
                onFork={() => handleFork(theme)}
                onActivate={() => activateMut.mutate(theme.id)}
                isActivating={activateMut.isPending}
                onEdit={() => navigate(`/sites/${siteId}/theme-engine/${theme.id}`)} />
            ))}
          </div>
        </div>
      )}

      {/* Custom Themes */}
      <div>
        <h2 className="text-sm font-semibold text-base-content/50 uppercase tracking-wider mb-3">
          Custom Themes {customThemes.length > 0 && `(${customThemes.length})`}
        </h2>
        {customThemes.length === 0 ? (
          <div className="text-center py-12 bg-base-100 rounded-xl border border-base-300">
            <Palette className="h-10 w-10 mx-auto mb-3 text-base-content/20" />
            <p className="text-sm text-base-content/40 mb-1">No custom themes yet</p>
            <p className="text-xs text-base-content/30">Fork a system theme to start customizing</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {customThemes.map(theme => (
              <ThemeCard key={theme.id} theme={theme} siteId={siteId}
                onFork={() => handleFork(theme)}
                onActivate={() => activateMut.mutate(theme.id)}
                isActivating={activateMut.isPending}
                onEdit={() => navigate(`/sites/${siteId}/theme-engine/${theme.id}`)} />
            ))}
          </div>
        )}
      </div>

      {/* Custom Fonts */}
      <CustomFontsSection siteId={siteId} />

      {/* Import dialog */}
      {showImport && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowImport(false)}>
          <div className="bg-base-100 rounded-xl shadow-2xl w-full max-w-lg p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-semibold mb-4">Import W3C Tokens JSON</h3>
            <textarea value={importJson} onChange={e => setImportJson(e.target.value)}
              className="w-full h-64 border border-base-300 rounded-lg p-3 font-mono text-xs"
              placeholder='Paste your W3C Design Tokens JSON here...' />
            <div className="flex justify-end gap-2 mt-4">
              <button onClick={() => setShowImport(false)} className="px-4 py-2 text-sm text-base-content/80 border rounded-lg">Cancel</button>
              <button onClick={handleImport} disabled={importMut.isPending || !importJson.trim()}
                className="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
                {importMut.isPending ? 'Importing...' : 'Import'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════
// Custom Fonts Section
// ═══════════════════════════════════════════

function CustomFontsSection({ siteId }: { siteId: string }) {
  const queryClient = useQueryClient();
  const fileRef = useRef<HTMLInputElement>(null);
  const [familyName, setFamilyName] = useState('');
  const [weight, setWeight] = useState(400);
  const [fontStyle, setFontStyle] = useState('normal');
  const [uploading, setUploading] = useState(false);

  const { data: fonts } = useQuery<any[]>({
    queryKey: ['custom-fonts', siteId],
    queryFn: () => customFonts.list(siteId).then((r: any) => r.data.data),
  });

  const deleteMut = useMutation({
    mutationFn: (fontId: string) => customFonts.remove(siteId, fontId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['custom-fonts', siteId] }),
  });

  const handleUpload = async () => {
    const file = fileRef.current?.files?.[0];
    if (!file || !familyName.trim()) return;
    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('font', file);
      fd.append('family', familyName.trim());
      fd.append('weight', String(weight));
      fd.append('style', fontStyle);
      await customFonts.upload(siteId, fd);
      queryClient.invalidateQueries({ queryKey: ['custom-fonts', siteId] });
      setFamilyName('');
      if (fileRef.current) fileRef.current.value = '';
    } catch (e: any) {
      alert(e?.response?.data?.message || 'Upload failed');
    } finally {
      setUploading(false);
    }
  };

  // Load custom fonts for preview
  useEffect(() => {
    (fonts || []).forEach((f: any) => {
      const url = customFonts.serveUrl(siteId, f.filename);
      const id = `custom-font-${f.id}`;
      if (document.getElementById(id)) return;
      const style = document.createElement('style');
      style.id = id;
      style.textContent = `@font-face { font-family: '${f.family}'; font-weight: ${f.weight}; font-style: ${f.style}; src: url('${url}') format('${f.format}'); font-display: swap; }`;
      document.head.appendChild(style);
    });
  }, [fonts, siteId]);

  const grouped = useMemo(() => {
    const map = new Map<string, any[]>();
    (fonts || []).forEach((f: any) => {
      const arr = map.get(f.family) || [];
      arr.push(f);
      map.set(f.family, arr);
    });
    return map;
  }, [fonts]);

  return (
    <div className="mb-8">
      <h2 className="text-sm font-semibold text-base-content/50 uppercase tracking-wider mb-3 flex items-center gap-2">
        <Type size={14} /> Custom Fonts
      </h2>
      <div className="bg-base-100 rounded-xl border border-base-300 p-4 mb-4">
        <div className="grid grid-cols-2 gap-3 mb-3">
          <div>
            <label className="text-xs text-base-content/50 mb-1 block">Font Family Name</label>
            <input type="text" value={familyName} onChange={e => setFamilyName(e.target.value)}
              placeholder="e.g. Montserrat" className="input input-bordered input-sm w-full text-xs" />
          </div>
          <div>
            <label className="text-xs text-base-content/50 mb-1 block">Font File</label>
            <input ref={fileRef} type="file" accept=".ttf,.woff,.woff2,.otf"
              className="file-input file-input-bordered file-input-sm w-full text-xs" />
          </div>
          <div>
            <label className="text-xs text-base-content/50 mb-1 block">Weight</label>
            <select value={weight} onChange={e => setWeight(Number(e.target.value))}
              className="select select-bordered select-sm w-full text-xs">
              {[100,200,300,400,500,600,700,800,900].map(w => (
                <option key={w} value={w}>{w}{w===400?' Regular':w===700?' Bold':''}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-xs text-base-content/50 mb-1 block">Style</label>
            <select value={fontStyle} onChange={e => setFontStyle(e.target.value)}
              className="select select-bordered select-sm w-full text-xs">
              <option value="normal">Normal</option>
              <option value="italic">Italic</option>
            </select>
          </div>
        </div>
        <button onClick={handleUpload} disabled={uploading || !familyName.trim()}
          className="btn btn-primary btn-sm w-full gap-1">
          {uploading ? <Loader2 size={12} className="animate-spin" /> : <Upload size={12} />}
          Upload Font
        </button>
      </div>
      {grouped.size === 0 ? (
        <div className="text-center py-6 text-xs text-base-content/40">No custom fonts uploaded yet</div>
      ) : (
        <div className="space-y-2">
          {Array.from(grouped.entries()).map(([family, variants]) => (
            <div key={family} className="bg-base-100 rounded-lg border border-base-300 p-3">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-semibold" style={{ fontFamily: `'${family}', sans-serif` }}>{family}</span>
                <span className="text-[10px] text-base-content/40">{variants.length} variant{variants.length > 1 ? 's' : ''}</span>
              </div>
              <p className="text-lg mb-2" style={{ fontFamily: `'${family}', sans-serif` }}>
                The quick brown fox jumps over the lazy dog
              </p>
              <div className="flex flex-wrap gap-1">
                {variants.map((v: any) => (
                  <div key={v.id} className="flex items-center gap-1 bg-base-200 rounded px-2 py-0.5">
                    <span className="text-[10px] text-base-content/50">{v.weight} {v.style}</span>
                    <button onClick={() => deleteMut.mutate(v.id)} className="text-base-content/30 hover:text-red-500"><Trash2 size={10} /></button>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function ThemeCard({ theme, siteId, onFork, onActivate, isActivating, onEdit }: {
  theme: ThemeItem;
  siteId: string;
  onFork: () => void;
  onActivate: () => void;
  isActivating: boolean;
  onEdit: () => void;
}) {
  const [previewOpen, setPreviewOpen] = useState(false);
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
  // live preview: the studio frame renders THIS theme with its real published
  // CSS (DesignTokenGenerator), so the picker shows exactly what will ship.
  const frameUrl = `/api/v1/sites/${siteId}/theme-engine/studio/frame/showcase?theme_id=${theme.id}`;
  const frameW = device === 'mobile' ? 390 : 1200;

  return (
    <div className={`bg-base-100 rounded-xl border p-4 hover:shadow-md transition-shadow ${
      theme.is_assigned ? 'border-primary/40 ring-1 ring-primary/20' : 'border-base-300'
    }`}>
      {/* Live thumbnail — a real, scaled-down render of the theme */}
      <div className="h-28 rounded-lg bg-base-200 mb-3 overflow-hidden relative cursor-pointer group/thumb border border-base-300"
        onClick={() => { setDevice('desktop'); setPreviewOpen(true); }}>
        <iframe title={`${theme.name} preview`} src={frameUrl} tabIndex={-1} aria-hidden
          loading="lazy"
          className="pointer-events-none border-0 origin-top-left"
          style={{ width: 1200, height: 933, transform: 'scale(0.3)' }} />
        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover/thumb:opacity-100 bg-black/40 transition-opacity">
          <span className="inline-flex items-center gap-1.5 text-white text-xs font-medium"><Eye className="h-4 w-4" /> Live preview</span>
        </div>
      </div>

      {/* Full live preview modal */}
      {previewOpen && (
        <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/60 p-6" onClick={() => setPreviewOpen(false)}>
          <div className="bg-base-100 rounded-2xl shadow-2xl w-full max-w-6xl flex flex-col max-h-[92vh]" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-5 py-3 border-b border-base-300">
              <div className="min-w-0">
                <h3 className="text-base font-semibold text-base-content flex items-center gap-2">
                  {theme.name}
                  {theme.is_assigned && <span className="badge badge-success badge-sm">Active</span>}
                  {theme.is_system && <span className="badge badge-info badge-sm">System</span>}
                </h3>
                {theme.description && <p className="text-xs text-base-content/50 truncate">{theme.description}</p>}
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <div className="join">
                  <button onClick={() => setDevice('desktop')}
                    className={`btn btn-xs join-item ${device === 'desktop' ? 'btn-primary' : 'btn-ghost'}`}>Desktop</button>
                  <button onClick={() => setDevice('mobile')}
                    className={`btn btn-xs join-item ${device === 'mobile' ? 'btn-primary' : 'btn-ghost'}`}>Mobile</button>
                </div>
                {!theme.is_assigned && (
                  <button onClick={() => { onActivate(); setPreviewOpen(false); }} disabled={isActivating}
                    className="btn btn-primary btn-xs gap-1"><Power className="h-3 w-3" /> Use this theme</button>
                )}
                <button onClick={() => setPreviewOpen(false)} className="btn btn-ghost btn-xs">Close</button>
              </div>
            </div>
            {/* the actual live render */}
            <div className="flex-1 overflow-auto bg-base-200 flex justify-center p-4">
              <iframe key={device} title={`${theme.name} full preview`} src={frameUrl}
                className="border border-base-300 bg-white shadow-sm"
                style={{ width: frameW, minHeight: 760, height: '100%', maxWidth: '100%' }} />
            </div>
          </div>
        </div>
      )}

      <div className="flex items-start justify-between mb-2">
        <div>
          <h3 className="font-semibold text-base-content">{theme.name}</h3>
          {theme.description && (
            <p className="text-xs text-base-content/40 mt-0.5 line-clamp-2">{theme.description}</p>
          )}
        </div>
        {theme.is_assigned && (
          <span className="badge badge-success badge-sm gap-1">
            <Power className="h-2.5 w-2.5" /> Active
          </span>
        )}
      </div>

      <div className="flex items-center gap-1.5 mb-3 flex-wrap">
        {theme.modes?.map(m => (
          <span key={m} className="badge badge-ghost badge-xs">{m}</span>
        ))}
        {theme.is_system && (
          <span className="badge badge-info badge-xs">System</span>
        )}
      </div>

      <div className="flex items-center gap-1.5">
        <button onClick={onEdit}
          className="flex-1 btn btn-ghost btn-xs gap-1">
          <Eye className="h-3 w-3" /> {theme.is_system ? 'View' : 'Edit'}
        </button>
        {theme.is_system && (
          <button onClick={onFork}
            className="btn btn-ghost btn-xs gap-1">
            <Copy className="h-3 w-3" /> Fork
          </button>
        )}
        {theme.is_assigned ? (
          <span className="btn btn-success btn-xs gap-1 no-animation">
            <Check className="h-3 w-3" /> Active
          </span>
        ) : (
          <button onClick={onActivate} disabled={isActivating}
            className="btn btn-primary btn-xs gap-1">
            <Power className="h-3 w-3" /> Activate
          </button>
        )}
      </div>
    </div>
  );
}
