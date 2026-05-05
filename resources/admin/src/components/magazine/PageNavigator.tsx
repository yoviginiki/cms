import React, { useState } from 'react';
import type { MagPageData } from '@/types/magazine';

interface PageNavigatorProps {
  pages: MagPageData[];
  currentPage: number;
  onChangePage: (n: number) => void;
  onAddPage: () => void;
  onDeletePage: (n: number) => void;
}

export default function PageNavigator({
  pages,
  currentPage,
  onChangePage,
  onAddPage,
  onDeletePage,
}: PageNavigatorProps) {
  const [confirmDelete, setConfirmDelete] = useState<number | null>(null);
  const [contextMenu, setContextMenu] = useState<{ page: number; x: number; y: number } | null>(null);

  const handleContextMenu = (e: React.MouseEvent, pageNumber: number) => {
    e.preventDefault();
    setContextMenu({ page: pageNumber, x: e.clientX, y: e.clientY });
  };

  const handleDeleteClick = (pageNumber: number) => {
    setContextMenu(null);
    setConfirmDelete(pageNumber);
  };

  const handleConfirmDelete = () => {
    if (confirmDelete !== null) {
      onDeletePage(confirmDelete);
      setConfirmDelete(null);
    }
  };

  return (
    <div
      className="flex flex-col items-center gap-2 py-3 px-2 bg-base-200/50 h-full overflow-y-auto w-20"
      onClick={() => { setContextMenu(null); }}
    >
      <span className="text-xs font-semibold uppercase tracking-wider text-base-content/50 mb-1">
        Pages
      </span>

      {pages.map((page) => {
        const isCurrent = page.pageNumber === currentPage;
        // Proportional thumbnail: max width 56px, scale height to ratio
        const ratio = page.pageSize.height / page.pageSize.width;
        const thumbW = 56;
        const thumbH = Math.round(thumbW * ratio);

        return (
          <div key={page.id} className="flex flex-col items-center gap-0.5">
            <button
              className={`relative rounded transition-all border-2 flex items-center justify-center
                ${isCurrent
                  ? 'border-primary shadow-sm shadow-primary/20'
                  : 'border-base-content/10 hover:border-base-content/25'
                }`}
              style={{
                width: thumbW,
                height: thumbH,
                backgroundColor: page.backgroundColor || '#ffffff',
                minHeight: 40,
                maxHeight: 80,
              }}
              onClick={() => onChangePage(page.pageNumber)}
              onContextMenu={(e) => handleContextMenu(e, page.pageNumber)}
              title={`Page ${page.pageNumber}`}
            >
              {/* Mini element indicators */}
              {page.elements.length > 0 && (
                <div className="absolute inset-1 flex flex-wrap gap-0.5 items-start content-start overflow-hidden opacity-30">
                  {page.elements.slice(0, 6).map((el) => (
                    <div
                      key={el.id}
                      className="bg-base-content/40 rounded-sm"
                      style={{
                        width: Math.max(4, (el.width / page.pageSize.width) * (thumbW - 8)),
                        height: Math.max(2, (el.height / page.pageSize.height) * (thumbH - 8)),
                      }}
                    />
                  ))}
                </div>
              )}
            </button>
            <span className={`text-xs tabular-nums ${isCurrent ? 'text-primary font-semibold' : 'text-base-content/50'}`}>
              {page.pageNumber}
            </span>
          </div>
        );
      })}

      {/* Add page button */}
      <button
        className="btn btn-ghost btn-sm btn-square rounded border border-dashed border-base-content/20 hover:border-primary/50 mt-1"
        onClick={onAddPage}
        title="Add page"
      >
        <svg className="w-4 h-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
        </svg>
      </button>

      {/* Context menu */}
      {contextMenu && (
        <div
          className="fixed z-50 bg-base-100 shadow-lg rounded-lg border border-base-content/10 py-1 min-w-32"
          style={{ left: contextMenu.x, top: contextMenu.y }}
          onClick={(e) => e.stopPropagation()}
        >
          <button
            className="w-full text-left px-3 py-1.5 text-sm hover:bg-base-content/5 text-error"
            onClick={() => handleDeleteClick(contextMenu.page)}
          >
            Delete page {contextMenu.page}
          </button>
        </div>
      )}

      {/* Delete confirmation modal */}
      {confirmDelete !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-base-content/20" onClick={() => setConfirmDelete(null)}>
          <div className="bg-base-100 rounded-xl shadow-xl p-5 max-w-xs mx-4" onClick={(e) => e.stopPropagation()}>
            <h4 className="font-semibold text-base-content mb-2">Delete page {confirmDelete}?</h4>
            <p className="text-sm text-base-content/60 mb-4">
              This will permanently remove the page and all its elements.
            </p>
            <div className="flex gap-2 justify-end">
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmDelete(null)}>
                Cancel
              </button>
              <button className="btn btn-error btn-sm" onClick={handleConfirmDelete}>
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
