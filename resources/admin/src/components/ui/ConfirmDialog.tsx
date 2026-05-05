import { useEffect, useRef } from 'react';

interface ConfirmDialogProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmText?: string;
  variant?: 'danger' | 'warning';
}

export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = 'Delete',
  variant = 'danger',
}: ConfirmDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    if (open) {
      dialogRef.current?.showModal();
    } else {
      dialogRef.current?.close();
    }
  }, [open]);

  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape' && open) {
        onClose();
      }
    }
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <dialog ref={dialogRef} className="modal" onClose={onClose}>
      <div className="modal-box bg-base-100 border border-base-300/50 max-w-sm">
        <h3 className="text-sm font-medium text-base-content">{title}</h3>
        <p className="mt-2 text-[13px] text-base-content/50 leading-relaxed">{message}</p>

        <div className="modal-action mt-6">
          <button onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">
            Cancel
          </button>
          <button
            onClick={() => { onConfirm(); onClose(); }}
            className={`btn btn-sm text-[12px] ${variant === 'danger' ? 'btn-error' : 'btn-warning'}`}
          >
            {confirmText}
          </button>
        </div>
      </div>
      <form method="dialog" className="modal-backdrop">
        <button onClick={onClose}>close</button>
      </form>
    </dialog>
  );
}
