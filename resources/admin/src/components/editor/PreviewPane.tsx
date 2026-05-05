import { useState, useRef, useEffect } from 'react';
import { Monitor, Tablet, Smartphone, Eye, Columns } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { preview } from '@/lib/api';

type ViewMode = 'editor' | 'preview' | 'split';
type Device = 'desktop' | 'tablet' | 'mobile';

const deviceWidths: Record<Device, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '375px',
};

interface PreviewPaneProps {
  siteId: string;
  contentType: 'pages' | 'posts';
  contentId: string;
}

export function PreviewPane({ siteId, contentType, contentId }: PreviewPaneProps) {
  const [mode, setMode] = useState<ViewMode>('editor');
  const [device, setDevice] = useState<Device>('desktop');
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const editorBlocks = useEditorStore((s) => s.blocks);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const previewUrl = contentType === 'pages'
    ? preview.page(siteId, contentId)
    : preview.post(siteId, contentId);

  // Watch for block changes — reload preview
  useEffect(() => {
    if (mode === 'editor') return;

    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      if (iframeRef.current) {
        iframeRef.current.src = previewUrl;
      }
    }, 1000);

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [editorBlocks, mode, previewUrl]);

  if (mode === 'editor') {
    return (
      <div className="flex items-center gap-1 mr-2">
        <button
          onClick={() => setMode('preview')}
          className="flex items-center gap-1 px-2 py-1 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
          title="Preview"
        >
          <Eye size={14} />
          Preview
        </button>
        <button
          onClick={() => setMode('split')}
          className="flex items-center gap-1 px-2 py-1 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded"
          title="Split view"
        >
          <Columns size={14} />
          Split
        </button>
      </div>
    );
  }

  const iframePanel = (
    <div className="relative flex-1 bg-gray-200 flex items-start justify-center p-4 overflow-auto">
      <div style={{ width: deviceWidths[device], maxWidth: '100%', transition: 'width 0.3s' }} className="bg-white shadow-lg rounded-lg overflow-hidden">
        <iframe
          ref={iframeRef}
          src={previewUrl}
          className="w-full border-0"
          style={{ height: 'calc(100vh - 120px)' }}
          title="Page preview"
        />
      </div>
    </div>
  );

  return (
    <div className="flex flex-col flex-1">
      {/* Preview toolbar */}
      <div className="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-200">
        <div className="flex items-center gap-1">
          <button onClick={() => setMode('editor')} className="px-2 py-1 text-xs rounded text-gray-500 hover:bg-gray-100">Editor</button>
          <button onClick={() => setMode('preview')} className={`px-2 py-1 text-xs rounded ${mode === 'preview' ? 'bg-white shadow-sm' : 'text-gray-500 hover:bg-gray-100'}`}>Preview</button>
          <button onClick={() => setMode('split')} className={`px-2 py-1 text-xs rounded ${mode === 'split' ? 'bg-white shadow-sm' : 'text-gray-500 hover:bg-gray-100'}`}>Split</button>
        </div>
        <div className="flex items-center gap-1">
          <button onClick={() => setDevice('desktop')} className={`p-1 rounded ${device === 'desktop' ? 'bg-blue-100 text-blue-600' : 'text-gray-400 hover:text-gray-600'}`} title="Desktop"><Monitor size={16} /></button>
          <button onClick={() => setDevice('tablet')} className={`p-1 rounded ${device === 'tablet' ? 'bg-blue-100 text-blue-600' : 'text-gray-400 hover:text-gray-600'}`} title="Tablet"><Tablet size={16} /></button>
          <button onClick={() => setDevice('mobile')} className={`p-1 rounded ${device === 'mobile' ? 'bg-blue-100 text-blue-600' : 'text-gray-400 hover:text-gray-600'}`} title="Mobile"><Smartphone size={16} /></button>
        </div>
      </div>
      {iframePanel}
    </div>
  );
}
