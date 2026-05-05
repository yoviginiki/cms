import type { FlipbookOptions, FlipState, FlipDirection, FlipEvent, FlipCallback, FlipAnimator } from './types';
import { realisticAnimator } from './modes/realistic';
import { minimalAnimator } from './modes/minimal';
import { GestureHandler } from './gestures';

const DEFAULTS: FlipbookOptions = {
  mode: 'realistic',
  aspect_ratio: '2:3',
  custom_width_px: null,
  custom_height_px: null,
  flipping_time_ms: 800,
  show_cover: true,
  max_shadow_opacity: 0.5,
  click_to_flip: true,
  swipe_to_flip: true,
  start_page: 0,
  responsive_breakpoint_px: 720,
  swipe_threshold_px: 30,
  pages: [],
};

export class Flipbook {
  private root: HTMLElement;
  private opts: FlipbookOptions;
  private state: FlipState;
  private listeners = new Map<FlipEvent, Set<FlipCallback>>();
  private pages: HTMLElement[] = [];
  private originalParents = new Map<HTMLElement, { parent: HTMLElement; nextSibling: Node | null }>();
  private viewport: HTMLElement | null = null;
  private spread: HTMLElement | null = null;
  private wellLeft: HTMLElement | null = null;
  private wellRight: HTMLElement | null = null;
  private pageStore: HTMLElement | null = null;
  private gestures: GestureHandler | null = null;
  private resizeObserver: ResizeObserver | null = null;
  private animator: FlipAnimator;
  private animationFrame = 0;
  private dragActive = false;
  private dragDirection: FlipDirection = 'forward';

  constructor(root: HTMLElement, options: Partial<FlipbookOptions> = {}) {
    this.root = root;
    this.opts = { ...DEFAULTS, ...options };

    // Collect pages from options or from DOM children
    if (this.opts.pages.length > 0) {
      this.pages = [...this.opts.pages];
    } else {
      this.pages = Array.from(root.children).filter(
        (c): c is HTMLElement => c instanceof HTMLElement && !c.classList.contains('ef-controls'),
      );
    }

    this.state = {
      currentPage: Math.max(0, Math.min(this.opts.start_page, this.pages.length - 1)),
      pageCount: this.pages.length,
      isAnimating: false,
      isSinglePage: false,
      mode: this.opts.mode,
    };

    this.animator = this.getAnimator();
    this.buildDOM();
    this.recalcSize();
    this.setupResponsive();
    this.render();

    this.gestures = new GestureHandler(this.root, this.opts, {
      onFlipNext: () => this.flipNext(),
      onFlipPrev: () => this.flipPrev(),
      onDragStart: (dir) => this.handleDragStart(dir),
      onDragMove: (p) => this.handleDragMove(p),
      onDragEnd: (c) => this.handleDragEnd(c),
      isAnimating: () => this.state.isAnimating,
    });

    this.emit('ready', {
      page: this.state.currentPage,
      previousPage: this.state.currentPage,
      direction: 'forward',
      mode: this.state.mode,
    });
  }

  // ─── Public API ───

  flipNext(): void {
    if (this.state.isAnimating) return;
    const step = this.state.isSinglePage ? 1 : 2;
    const next = this.state.currentPage + step;
    if (next >= this.state.pageCount) return;
    this.animateFlip('forward', next);
  }

  flipPrev(): void {
    if (this.state.isAnimating) return;
    const step = this.state.isSinglePage ? 1 : 2;
    const next = this.state.currentPage - step;
    if (next < 0) return;
    this.animateFlip('backward', next);
  }

  flipTo(pageIndex: number): void {
    if (this.state.isAnimating) return;
    const idx = Math.max(0, Math.min(pageIndex, this.state.pageCount - 1));
    // Snap to even page in spread mode
    const target = this.state.isSinglePage ? idx : (idx % 2 === 0 ? idx : idx - 1);
    if (target === this.state.currentPage) return;
    const dir: FlipDirection = target > this.state.currentPage ? 'forward' : 'backward';
    this.animateFlip(dir, target);
  }

  getCurrentPage(): number {
    return this.state.currentPage;
  }

  getPageCount(): number {
    return this.state.pageCount;
  }

  /** Update pages from new DOM children (for live editor preview). */
  updatePages(newPages: HTMLElement[]): void {
    if (this.state.isAnimating) return;

    // Remove old page classes and attributes
    this.pages.forEach(p => {
      p.classList.remove('ef-page', 'ef-cover');
      p.removeAttribute('aria-hidden');
      p.removeAttribute('role');
      p.removeAttribute('aria-label');
      delete p.dataset.pageIndex;
    });

    this.pages = newPages;
    this.state.pageCount = newPages.length;
    this.state.currentPage = Math.min(this.state.currentPage, Math.max(0, newPages.length - 1));

    // Snap to even in spread mode
    if (!this.state.isSinglePage && this.state.currentPage % 2 !== 0) {
      this.state.currentPage = Math.max(0, this.state.currentPage - 1);
    }

    // Set up new pages
    this.pages.forEach((page, i) => {
      page.classList.add('ef-page');
      page.dataset.pageIndex = String(i);
      page.setAttribute('role', 'group');
      page.setAttribute('aria-label', `Page ${i + 1}`);
      if (this.opts.show_cover && (i === 0 || i === this.pages.length - 1)) {
        page.classList.add('ef-cover');
      }
    });

    this.render();
  }

  /** Update options without recreating (for live editor settings changes). */
  updateOptions(opts: Partial<FlipbookOptions>): void {
    Object.assign(this.opts, opts);
    if (opts.mode && opts.mode !== this.state.mode) {
      this.setMode(opts.mode);
    }
    if (opts.aspect_ratio || opts.custom_width_px !== undefined || opts.custom_height_px !== undefined) {
      this.updateRootClasses();
    }
  }

  setMode(mode: 'realistic' | 'minimal'): void {
    if (mode === this.state.mode) return;
    if (this.state.isAnimating) return;
    this.state.mode = mode;
    this.opts.mode = mode;
    this.animator = this.getAnimator();
    this.updateRootClasses();
    this.emit('modechange', {
      page: this.state.currentPage,
      previousPage: this.state.currentPage,
      direction: 'forward',
      mode,
    });
  }

  on(event: FlipEvent, cb: FlipCallback): void {
    if (!this.listeners.has(event)) this.listeners.set(event, new Set());
    this.listeners.get(event)!.add(cb);
  }

  off(event: FlipEvent, cb: FlipCallback): void {
    this.listeners.get(event)?.delete(cb);
  }

  destroy(): void {
    // Cancel any running animation
    if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
    this.animator.cleanup(this.root);

    // Gestures
    this.gestures?.destroy();
    this.gestures = null;

    // Responsive
    this.resizeObserver?.disconnect();
    this.resizeObserver = null;

    // Restore pages to original parents
    this.pages.forEach(page => {
      const orig = this.originalParents.get(page);
      if (orig) {
        if (orig.nextSibling) {
          orig.parent.insertBefore(page, orig.nextSibling);
        } else {
          orig.parent.appendChild(page);
        }
      }
      // Remove data attributes we added
      delete page.dataset.pageIndex;
      page.classList.remove('ef-page', 'ef-cover');
      page.removeAttribute('aria-hidden');
      page.removeAttribute('inert');
      page.removeAttribute('role');
      page.removeAttribute('aria-label');
    });
    this.originalParents.clear();

    // Remove DOM structures we created
    this.viewport?.remove();
    this.root.querySelector('.ef-controls')?.remove();
    this.root.querySelector('.ef-sr-only')?.remove();
    this.root.classList.remove('ef-root', 'ef-single', 'ef-realistic', 'ef-minimal');
    this.root.removeAttribute('role');
    this.root.removeAttribute('aria-roledescription');
    this.root.style.removeProperty('--ef-page-w');
    this.root.style.removeProperty('--ef-page-h');

    this.listeners.clear();
  }

  // ─── DOM setup ───

  private buildDOM(): void {
    this.root.classList.add('ef-root');
    this.root.setAttribute('role', 'region');
    this.root.setAttribute('aria-roledescription', 'flipbook');

    // Compute dimensions
    const { w, h } = this.computeDimensions();

    // Create viewport and spread
    this.viewport = document.createElement('div');
    this.viewport.className = 'ef-viewport';

    this.viewport.style.width = '100%';

    this.spread = document.createElement('div');
    this.spread.className = 'ef-spread';
    this.spread.style.position = 'absolute';
    this.spread.style.top = '0';
    this.spread.style.left = '0';
    this.spread.style.width = '100%';
    this.spread.style.height = '100%';

    this.wellLeft = document.createElement('div');
    this.wellLeft.className = 'ef-well ef-well-left';

    this.wellRight = document.createElement('div');
    this.wellRight.className = 'ef-well ef-well-right';

    // Hidden container to keep all pages in the DOM (animators need to query them)
    this.pageStore = document.createElement('div');
    this.pageStore.className = 'ef-page-store';
    this.pageStore.style.display = 'none';

    this.spread.appendChild(this.wellLeft);
    this.spread.appendChild(this.wellRight);
    this.viewport.appendChild(this.spread);
    this.viewport.appendChild(this.pageStore);

    // Save original parents and move pages into our structure
    this.pages.forEach((page, i) => {
      this.originalParents.set(page, {
        parent: page.parentElement!,
        nextSibling: page.nextSibling,
      });
      page.classList.add('ef-page');
      page.dataset.pageIndex = String(i);
      page.setAttribute('role', 'group');
      page.setAttribute('aria-label', `Page ${i + 1}`);

      // Mark covers
      if (this.opts.show_cover && (i === 0 || i === this.pages.length - 1)) {
        page.classList.add('ef-cover');
      }
    });

    // Insert viewport before any remaining children
    this.root.prepend(this.viewport);
    this.updateRootClasses();
  }

  /** Fit the viewport to fill available space, respecting aspect ratio. */
  private recalcSize(): void {
    if (!this.viewport) return;
    const { w, h } = this.computeDimensions();
    const ratio = h / w;
    const spreadPages = this.state.isSinglePage ? 1 : 2;

    const containerWidth = this.root.clientWidth || 800;
    // Use parent height if available, otherwise window minus small header margin
    const parentH = this.root.parentElement?.clientHeight;
    const availHeight = Math.max(200, (parentH && parentH > 100) ? parentH : window.innerHeight - 50);

    const pageWFromWidth = containerWidth / spreadPages;
    const heightFromWidth = pageWFromWidth * ratio;

    if (heightFromWidth <= availHeight) {
      this.viewport.style.width = '100%';
      this.viewport.style.height = heightFromWidth + 'px';
      this.viewport.style.margin = '';
    } else {
      const fittedPageW = availHeight / ratio;
      const fittedSpreadW = fittedPageW * spreadPages;
      this.viewport.style.width = fittedSpreadW + 'px';
      this.viewport.style.height = availHeight + 'px';
      this.viewport.style.margin = '0 auto';
    }
  }

  private computeDimensions(): { w: number; h: number } {
    if (this.opts.aspect_ratio === 'custom') {
      return {
        w: this.opts.custom_width_px || 400,
        h: this.opts.custom_height_px || 600,
      };
    }
    const [wStr, hStr] = this.opts.aspect_ratio.split(':');
    return { w: Number(wStr), h: Number(hStr) };
  }

  private updateRootClasses(): void {
    this.root.classList.toggle('ef-single', this.state.isSinglePage);
    this.root.classList.toggle('ef-realistic', this.state.mode === 'realistic');
    this.root.classList.toggle('ef-minimal', this.state.mode === 'minimal');

    if (this.viewport) {
      this.recalcSize();
    }

    // Perspective for realistic mode
    if (this.spread) {
      this.spread.style.perspective = this.state.mode === 'realistic' ? '1200px' : 'none';
      this.spread.style.transformStyle = this.state.mode === 'realistic' ? 'preserve-3d' : 'flat';
    }
  }

  // ─── Page rendering ───

  private render(): void {
    if (!this.wellLeft || !this.wellRight) return;

    // Move all pages to the hidden store and mark as hidden/inert
    this.pages.forEach(p => {
      this.pageStore!.appendChild(p);
      p.setAttribute('aria-hidden', 'true');
      p.setAttribute('inert', '');
    });

    const cur = this.state.currentPage;

    if (this.state.isSinglePage) {
      // Single page mode: one page fills the viewport
      const page = this.pages[cur];
      if (page) {
        page.removeAttribute('aria-hidden');
        page.removeAttribute('inert');
        this.wellLeft.appendChild(page);
      }
      this.wellRight.style.display = 'none';
    } else {
      this.wellRight.style.display = '';
      // Spread mode: left page = even index, right page = odd
      const leftIdx = cur % 2 === 0 ? cur : cur - 1;
      const rightIdx = leftIdx + 1;

      const leftPage = this.pages[leftIdx];
      const rightPage = this.pages[rightIdx];

      if (leftPage) {
        leftPage.removeAttribute('aria-hidden');
        leftPage.removeAttribute('inert');
        this.wellLeft.appendChild(leftPage);
      }
      if (rightPage) {
        rightPage.removeAttribute('aria-hidden');
        rightPage.removeAttribute('inert');
        this.wellRight.appendChild(rightPage);
      }
    }
  }

  // ─── Animation ───

  private getAnimator(): FlipAnimator {
    return this.state.mode === 'realistic' ? realisticAnimator : minimalAnimator;
  }

  private animateFlip(direction: FlipDirection, targetPage: number): void {
    this.state.isAnimating = true;
    const prevPage = this.state.currentPage;

    // Setup animation layers
    this.animator.setup(this.root, this.state, direction, this.opts, this.pages);

    const duration = this.opts.flipping_time_ms;
    const start = performance.now();

    // Easing: cubic-bezier(0.645, 0.045, 0.355, 1) for realistic, ease-in-out for minimal
    const ease = this.state.mode === 'realistic'
      ? (t: number) => cubicBezier(0.645, 0.045, 0.355, 1, t)
      : (t: number) => t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2; // easeInOutQuad

    const tick = (now: number) => {
      const elapsed = now - start;
      const rawProgress = Math.min(elapsed / duration, 1);
      const progress = ease(rawProgress);

      this.animator.apply(this.root, this.state, direction, progress, this.opts);

      if (rawProgress < 1) {
        this.animationFrame = requestAnimationFrame(tick);
      } else {
        this.completeFlip(direction, targetPage, prevPage);
      }
    };

    this.animationFrame = requestAnimationFrame(tick);
  }

  private completeFlip(direction: FlipDirection, targetPage: number, prevPage: number): void {
    this.animator.cleanup(this.root);
    this.state.currentPage = targetPage;
    this.state.isAnimating = false;
    this.render();

    this.gestures?.announcePageChange(targetPage, this.state.pageCount);
    this.emit('flip', { page: targetPage, previousPage: prevPage, direction, mode: this.state.mode });
  }

  // ─── Drag-based animation ───

  private handleDragStart(direction: FlipDirection): void {
    if (this.state.isAnimating) return;
    this.dragActive = true;
    this.dragDirection = direction;

    // Check bounds
    const step = this.state.isSinglePage ? 1 : 2;
    const next = direction === 'forward'
      ? this.state.currentPage + step
      : this.state.currentPage - step;
    if (next < 0 || next >= this.state.pageCount) {
      this.dragActive = false;
      return;
    }

    this.state.isAnimating = true;
    this.animator.setup(this.root, this.state, direction, this.opts, this.pages);
  }

  private handleDragMove(progress: number): void {
    if (!this.dragActive) return;
    this.animator.apply(this.root, this.state, this.dragDirection, progress, this.opts);
  }

  private handleDragEnd(completed: boolean): void {
    if (!this.dragActive) return;
    this.dragActive = false;

    const step = this.state.isSinglePage ? 1 : 2;
    const targetPage = this.dragDirection === 'forward'
      ? this.state.currentPage + step
      : this.state.currentPage - step;
    const prevPage = this.state.currentPage;

    if (completed && targetPage >= 0 && targetPage < this.state.pageCount) {
      // Animate to completion from current drag position
      // For simplicity in v1: snap to complete
      this.completeFlip(this.dragDirection, targetPage, prevPage);
    } else {
      // Spring back — cancel
      this.animator.cleanup(this.root);
      this.state.isAnimating = false;
      this.render();
    }
  }

  // ─── Responsive ───

  private setupResponsive(): void {
    const check = () => {
      const width = this.root.clientWidth;
      const shouldBeSingle = width < this.opts.responsive_breakpoint_px;
      if (shouldBeSingle !== this.state.isSinglePage) {
        if (this.state.isAnimating) return;
        this.state.isSinglePage = shouldBeSingle;
        this.updateRootClasses();
        if (!this.state.isSinglePage && this.state.currentPage % 2 !== 0) {
          this.state.currentPage = Math.max(0, this.state.currentPage - 1);
        }
        this.render();
      }
      // Always recalc size on resize (handles fullscreen toggle, window resize)
      this.recalcSize();
    };

    this.resizeObserver = new ResizeObserver(check);
    this.resizeObserver.observe(this.root);
    check();
  }

  // ─── Events ───

  private emit(event: FlipEvent, data: any): void {
    this.listeners.get(event)?.forEach(cb => {
      try { cb(data); } catch (e) { console.error('Flipbook event error:', e); }
    });
  }
}

// ─── Cubic bezier approximation ───
function cubicBezier(x1: number, y1: number, x2: number, y2: number, t: number): number {
  // Newton–Raphson for finding t on the bezier given x
  const cx = 3 * x1;
  const bx = 3 * (x2 - x1) - cx;
  const ax = 1 - cx - bx;
  const cy = 3 * y1;
  const by = 3 * (y2 - y1) - cy;
  const ay = 1 - cy - by;

  let x = t;
  for (let i = 0; i < 8; i++) {
    const currentX = ((ax * x + bx) * x + cx) * x - t;
    if (Math.abs(currentX) < 1e-6) break;
    const dx = (3 * ax * x + 2 * bx) * x + cx;
    if (Math.abs(dx) < 1e-6) break;
    x -= currentX / dx;
  }

  return ((ay * x + by) * x + cy) * x;
}
