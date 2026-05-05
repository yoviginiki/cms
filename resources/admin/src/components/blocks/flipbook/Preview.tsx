import React from 'react';
import { BookOpen, FileText, FolderOpen, Plus, Trash2, Copy, GripVertical } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';
import type { FlipbookBlockData } from './definition';
import { useEditorStore } from '@/stores/editorStore';

export const FlipbookPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as unknown as FlipbookBlockData;
  const pages = block.children || [];
  const source = data.source ?? 'pdf';
  const pdfUrl = data.pdf_url || (data.pdf_asset_id ? `/api/v1/assets/${data.pdf_asset_id}/serve` : '');
  const hasPdf = source === 'pdf' && !!pdfUrl;
  const hasCategory = source === 'category' && !!data.category_id;

  const addBlock = useEditorStore((s) => s.addBlock);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);

  return (
    <div className="border border-base-300/30 rounded-lg overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-3 py-2 bg-base-200/30 border-b border-base-300/20">
        <div className="flex items-center gap-2">
          <BookOpen size={16} className="text-base-content/30" />
          <span className="text-[12px] font-medium text-base-content/70">Flipbook</span>
          <span className="text-[10px] text-base-content/30">{data.mode}</span>
        </div>
      </div>

      {/* PDF mode */}
      {hasPdf && (
        <div className="p-3 bg-base-200/20">
          <div className="flex items-center gap-2 text-[12px] text-base-content/60">
            <FileText size={14} className="text-primary/60" />
            <span>PDF loaded — pages render from document</span>
          </div>
          <p className="text-[10px] text-base-content/30 mt-1 truncate">{pdfUrl}</p>
        </div>
      )}

      {/* Category mode */}
      {hasCategory && (
        <div className="p-3 bg-base-200/20">
          <div className="flex items-center gap-2 text-[12px] text-base-content/60">
            <FolderOpen size={14} className="text-primary/60" />
            <span>Category articles — each post becomes one page</span>
          </div>
          <p className="text-[10px] text-base-content/30 mt-1">
            Order: {data.posts_order?.replace('_', ' ')} &middot; Max: {data.posts_limit ?? 50}
          </p>
        </div>
      )}

      {/* Child pages (blocks mode) */}
      {source === 'children' && (
        <>
          <div className="p-2 space-y-1">
            {pages.length === 0 ? (
              <p className="text-[12px] text-base-content/30 text-center py-6">
                Upload a PDF in properties, or add child blocks as pages.
              </p>
            ) : (
              pages.map((child, i) => (
                <div key={child.id}
                  className="flex items-center gap-2 px-2 py-1.5 rounded bg-base-100 border border-base-300/20 hover:border-base-300/40 transition-colors group"
                  onClick={(e) => { e.stopPropagation(); selectBlock(child.id); }}>
                  <GripVertical size={12} className="text-base-content/20 cursor-grab shrink-0" />
                  <span className="text-[10px] text-base-content/30 font-mono w-5 shrink-0">{i + 1}</span>
                  <span className="text-[11px] text-base-content/60 truncate flex-1">
                    {child.type.replace(/-/g, ' ')}
                  </span>
                  <div className="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onClick={(e) => { e.stopPropagation(); duplicateBlock(child.id); }}
                      className="btn btn-ghost btn-xs btn-square"><Copy size={10} /></button>
                    <button onClick={(e) => { e.stopPropagation(); removeBlock(child.id); }}
                      className="btn btn-ghost btn-xs btn-square text-error"><Trash2 size={10} /></button>
                  </div>
                </div>
              ))
            )}
          </div>
          {source === 'children' && (
            <div className="p-2 pt-0">
              <button onClick={(e) => { e.stopPropagation(); addBlock('paragraph', block.id); }}
                className="btn btn-ghost btn-xs w-full border border-dashed border-base-300/30 gap-1 text-[11px] text-base-content/40">
                <Plus size={12} /> Add page
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
};
