export interface FlipbookOptions {
  mode: 'realistic' | 'minimal';
  aspect_ratio: string;
  custom_width_px?: number | null;
  custom_height_px?: number | null;
  flipping_time_ms: number;
  show_cover: boolean;
  max_shadow_opacity: number;
  click_to_flip: boolean;
  swipe_to_flip: boolean;
  start_page: number;
  responsive_breakpoint_px: number;
  swipe_threshold_px: number;
  pages: HTMLElement[];
}

export interface FlipState {
  currentPage: number;
  pageCount: number;
  isAnimating: boolean;
  isSinglePage: boolean;
  mode: 'realistic' | 'minimal';
}

export type FlipDirection = 'forward' | 'backward';

export type FlipEvent = 'flip' | 'ready' | 'modechange';

export interface FlipEventData {
  page: number;
  previousPage: number;
  direction: FlipDirection;
  mode: 'realistic' | 'minimal';
}

export type FlipCallback = (data: FlipEventData) => void;

export interface FlipAnimator {
  apply(
    root: HTMLElement,
    state: FlipState,
    direction: FlipDirection,
    progress: number,
    options: FlipbookOptions,
  ): void;
  setup(root: HTMLElement, state: FlipState, direction: FlipDirection, options: FlipbookOptions, allPages: HTMLElement[]): void;
  cleanup(root: HTMLElement): void;
}
