import { createContext, useContext, useState, useCallback } from 'react';
import { CheckCircle, XCircle, Info, X } from 'lucide-react';

type ToastType = 'success' | 'error' | 'info';

interface ToastMessage {
  id: string;
  type: ToastType;
  message: string;
}

interface ToastContextValue {
  toast: (opts: { type: ToastType; message: string; duration?: number }) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return ctx;
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const toast = useCallback(
    ({ type, message, duration = 3000 }: { type: ToastType; message: string; duration?: number }) => {
      const id = crypto.randomUUID();
      setToasts((prev) => [...prev, { id, type, message }]);
      setTimeout(() => removeToast(id), duration);
    },
    [removeToast],
  );

  return (
    <ToastContext.Provider value={{ toast }}>
      {children}

      <div className="toast toast-end toast-bottom z-[100]">
        {toasts.map((t) => (
          <div key={t.id} className={`alert ${t.type === 'success' ? 'alert-success' : t.type === 'error' ? 'alert-error' : 'alert-info'} shadow-elev-2 py-2 px-3 text-[13px] animate-slide-in-right`}>
            {t.type === 'success' ? <CheckCircle size={15} /> : t.type === 'info' ? <Info size={15} /> : <XCircle size={15} />}
            <span>{t.message}</span>
            <button onClick={() => removeToast(t.id)} className="btn btn-ghost btn-xs btn-square opacity-60 hover:opacity-100">
              <X size={12} />
            </button>
          </div>
        ))}
      </div>

      <style>{`
        @keyframes slide-in-right {
          from { opacity: 0; transform: translateX(16px); }
          to { opacity: 1; transform: translateX(0); }
        }
        .animate-slide-in-right { animation: slide-in-right 200ms ease-out; }
      `}</style>
    </ToastContext.Provider>
  );
}
