import type { FlipAnimator, FlipState, FlipDirection, FlipbookOptions } from '../types';

/**
 * Minimal flip mode: subtle 3D rotation with distant perspective.
 */
export const minimalAnimator: FlipAnimator = {
  setup(root, state, direction, options, allPages) {
    const spread = root.querySelector('.ef-spread') as HTMLElement;
    if (!spread) return;

    const isForward = direction === 'forward';
    const wellClass = isForward ? '.ef-well-right' : '.ef-well-left';
    const well = spread.querySelector(wellClass) as HTMLElement;
    if (!well) return;

    const currentPage = well.querySelector('.ef-page:not([aria-hidden="true"])') as HTMLElement;
    if (!currentPage) return;

    // Find next page from allPages array
    const isSingle = state.isSinglePage;
    let nextIdx: number;
    if (isForward) {
      nextIdx = isSingle ? state.currentPage + 1 : state.currentPage + 2;
    } else {
      nextIdx = isSingle ? state.currentPage - 1 : state.currentPage - 1;
    }
    const nextPageEl = allPages[nextIdx] || null;

    // Create flip container
    const flipContainer = document.createElement('div');
    flipContainer.className = 'ef-flip-container';
    flipContainer.style.perspective = '3000px';
    flipContainer.style.transformStyle = 'flat';
    flipContainer.style.transformOrigin = isForward ? 'left center' : 'right center';

    const flipEl = document.createElement('div');
    flipEl.className = 'ef-flip-front';
    flipEl.style.transformOrigin = isForward ? 'left center' : 'right center';
    flipEl.style.transform = 'rotateY(0deg)';
    flipEl.style.transition = 'none';
    flipEl.style.backfaceVisibility = 'hidden';

    const frontClone = currentPage.cloneNode(true) as HTMLElement;
    frontClone.style.position = 'relative';
    frontClone.style.display = '';
    frontClone.removeAttribute('aria-hidden');
    frontClone.removeAttribute('inert');
    flipEl.appendChild(frontClone);

    flipContainer.appendChild(flipEl);

    well.style.overflow = 'visible';
    well.appendChild(flipContainer);
    currentPage.style.visibility = 'hidden';

    root.dataset.efFlipActive = '1';
    (root as any).__efFlip = {
      flipContainer, flipEl, currentPage, well, isForward,
      nextPageEl, swappedAtMidpoint: false,
    };
  },

  apply(root, state, direction, progress, options) {
    const refs = (root as any).__efFlip;
    if (!refs) return;

    const { flipEl, isForward, nextPageEl } = refs;
    const angle = isForward ? -180 * progress : 180 * progress;

    flipEl.style.transform = `perspective(3000px) rotateY(${angle}deg)`;

    // Box shadow grows during flip
    const lift = Math.sin(progress * Math.PI);
    flipEl.style.boxShadow = `0 ${4 + 12 * lift}px ${8 + 20 * lift}px rgba(0,0,0,${0.08 + 0.12 * lift})`;

    // Swap content at midpoint
    if (progress >= 0.5 && !refs.swappedAtMidpoint) {
      refs.swappedAtMidpoint = true;
      flipEl.innerHTML = '';
      if (nextPageEl) {
        const clone = nextPageEl.cloneNode(true) as HTMLElement;
        clone.style.position = 'relative';
        clone.style.display = '';
        clone.removeAttribute('aria-hidden');
        clone.removeAttribute('inert');
        clone.style.transform = 'scaleX(-1)';
        flipEl.appendChild(clone);
      }
    } else if (progress < 0.5 && refs.swappedAtMidpoint) {
      refs.swappedAtMidpoint = false;
      flipEl.innerHTML = '';
      const clone = refs.currentPage.cloneNode(true) as HTMLElement;
      clone.style.display = '';
      clone.removeAttribute('aria-hidden');
      clone.removeAttribute('inert');
      flipEl.appendChild(clone);
    }
  },

  cleanup(root) {
    const refs = (root as any).__efFlip;
    if (!refs) return;

    const { flipContainer, currentPage, well } = refs;
    currentPage.style.visibility = '';
    well.style.overflow = '';
    flipContainer.remove();
    delete (root as any).__efFlip;
    delete root.dataset.efFlipActive;
  },
};
