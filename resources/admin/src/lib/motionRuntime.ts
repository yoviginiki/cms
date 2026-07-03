import gsap from 'gsap';

/**
 * Bridge to THE motion-runtime module (resources/js/motion-runtime.js) — the
 * exact file published pages load. One timeline builder for editor preview
 * and published output; no fork.
 */
export interface StillopressMotion {
  buildSlideTimeline: (slideEl: HTMLElement, slideConfig: unknown, phase: 'in' | 'out') => gsap.core.Timeline;
  split: (el: HTMLElement, mode: string) => HTMLElement[];
  PRESETS: Record<string, unknown>;
}

let runtime: StillopressMotion | null = null;

export async function loadMotionRuntime(): Promise<StillopressMotion> {
  if (!runtime) {
    (window as unknown as { gsap: typeof gsap }).gsap = gsap;
    // side-effect import registers window.StillopressMotion
    // @ts-expect-error — plain JS IIFE shipped to published pages, no types
    await import('../../../js/motion-runtime.js');
    runtime = (window as unknown as { StillopressMotion: StillopressMotion }).StillopressMotion;
  }
  return runtime;
}

export { gsap };
