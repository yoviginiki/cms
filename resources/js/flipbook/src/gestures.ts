import type { FlipbookOptions } from './types';

const INTERACTIVE_SELECTORS = 'a, button, input, select, textarea, [role="button"], [contenteditable]';

interface GestureCallbacks {
  onFlipNext(): void;
  onFlipPrev(): void;
  onDragStart(direction: 'forward' | 'backward'): void;
  onDragMove(progress: number): void;
  onDragEnd(completed: boolean): void;
  isAnimating(): boolean;
}

export class GestureHandler {
  private root: HTMLElement;
  private opts: FlipbookOptions;
  private cb: GestureCallbacks;
  private cleanup: (() => void)[] = [];
  private dragging = false;
  private dragStartX = 0;
  private dragDirection: 'forward' | 'backward' = 'forward';
  private statusEl: HTMLElement | null = null;

  constructor(root: HTMLElement, opts: FlipbookOptions, cb: GestureCallbacks) {
    this.root = root;
    this.opts = opts;
    this.cb = cb;
    this.setup();
  }

  private setup(): void {
    // Keyboard (when root has focus)
    this.root.setAttribute('tabindex', '0');
    const onKey = (e: KeyboardEvent) => {
      if (this.cb.isAnimating()) return;
      switch (e.key) {
        case 'ArrowRight': case 'PageDown': e.preventDefault(); this.cb.onFlipNext(); break;
        case 'ArrowLeft': case 'PageUp': e.preventDefault(); this.cb.onFlipPrev(); break;
        case 'Home': e.preventDefault(); /* flipTo(0) handled by Flipbook */ break;
        case 'End': e.preventDefault(); break;
      }
    };
    this.root.addEventListener('keydown', onKey);
    this.cleanup.push(() => this.root.removeEventListener('keydown', onKey));

    // Click to flip
    if (this.opts.click_to_flip) {
      const onClick = (e: MouseEvent) => {
        if (this.cb.isAnimating()) return;
        // Don't capture clicks on interactive children
        const target = e.target as HTMLElement;
        if (target.closest(INTERACTIVE_SELECTORS)) return;

        const rect = this.root.getBoundingClientRect();
        const x = e.clientX - rect.left;
        if (x > rect.width / 2) {
          this.cb.onFlipNext();
        } else {
          this.cb.onFlipPrev();
        }
      };
      this.root.addEventListener('click', onClick);
      this.cleanup.push(() => this.root.removeEventListener('click', onClick));
    }

    // Swipe / drag
    if (this.opts.swipe_to_flip) {
      this.setupSwipe();
    }

    // Screen-reader status
    this.statusEl = document.createElement('div');
    this.statusEl.setAttribute('aria-live', 'polite');
    this.statusEl.setAttribute('role', 'status');
    this.statusEl.className = 'ef-sr-only';
    this.root.appendChild(this.statusEl);
    this.cleanup.push(() => this.statusEl?.remove());
  }

  private setupSwipe(): void {
    let startX = 0;
    let startY = 0;
    let locked = false; // Once we determine horizontal vs vertical, lock
    let isHorizontal = false;
    const threshold = this.opts.swipe_threshold_px || 30;

    const onPointerDown = (e: PointerEvent) => {
      if (this.cb.isAnimating()) return;
      if ((e.target as HTMLElement).closest(INTERACTIVE_SELECTORS)) return;
      this.dragging = true;
      locked = false;
      isHorizontal = false;
      startX = e.clientX;
      startY = e.clientY;
      this.root.setPointerCapture(e.pointerId);
    };

    const onPointerMove = (e: PointerEvent) => {
      if (!this.dragging) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;

      // Determine direction lock
      if (!locked && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
        locked = true;
        isHorizontal = Math.abs(dx) > Math.abs(dy);
        if (isHorizontal) {
          this.dragDirection = dx < 0 ? 'forward' : 'backward';
          this.dragStartX = startX;
          this.cb.onDragStart(this.dragDirection);
        }
      }

      if (!locked || !isHorizontal) return;
      e.preventDefault();

      const rootWidth = this.root.getBoundingClientRect().width;
      const travel = Math.abs(e.clientX - this.dragStartX);
      const progress = Math.min(travel / (rootWidth * 0.4), 1);
      this.cb.onDragMove(progress);
    };

    const onPointerUp = (e: PointerEvent) => {
      if (!this.dragging) return;
      this.dragging = false;

      if (!locked || !isHorizontal) return;

      const dx = e.clientX - this.dragStartX;
      const completed = Math.abs(dx) > threshold;
      this.cb.onDragEnd(completed);
    };

    this.root.addEventListener('pointerdown', onPointerDown);
    this.root.addEventListener('pointermove', onPointerMove);
    this.root.addEventListener('pointerup', onPointerUp);
    this.root.addEventListener('pointercancel', onPointerUp);
    this.root.style.touchAction = 'pan-y'; // Allow vertical scroll, capture horizontal

    this.cleanup.push(() => {
      this.root.removeEventListener('pointerdown', onPointerDown);
      this.root.removeEventListener('pointermove', onPointerMove);
      this.root.removeEventListener('pointerup', onPointerUp);
      this.root.removeEventListener('pointercancel', onPointerUp);
    });
  }

  announcePageChange(page: number, total: number): void {
    if (this.statusEl) {
      this.statusEl.textContent = `Page ${page + 1} of ${total}`;
    }
  }

  destroy(): void {
    this.cleanup.forEach(fn => fn());
    this.cleanup = [];
    this.root.removeAttribute('tabindex');
  }
}
