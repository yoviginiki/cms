import { Component, type ErrorInfo, type ReactNode } from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, info: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/**
 * Reusable error boundary that prevents full-page crashes.
 * Shows a friendly error message with retry option.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, error: null };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('[ErrorBoundary]', error, info.componentStack);
    this.props.onError?.(error, info);
  }

  handleRetry = () => {
    this.setState({ hasError: false, error: null });
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) return this.props.fallback;

      return (
        <div className="flex flex-col items-center justify-center p-8 text-center gap-3">
          <AlertTriangle size={32} className="text-warning" />
          <h3 className="text-sm font-medium text-base-content/80">Something went wrong</h3>
          <p className="text-xs text-base-content/40 max-w-sm">
            {this.state.error?.message || 'An unexpected error occurred. Try refreshing this section.'}
          </p>
          <button onClick={this.handleRetry} className="btn btn-ghost btn-sm gap-1.5 mt-2">
            <RefreshCw size={14} /> Try again
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
