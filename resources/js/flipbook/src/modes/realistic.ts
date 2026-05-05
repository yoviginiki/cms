import type { FlipAnimator, FlipState, FlipDirection, FlipbookOptions } from '../types';

/**
 * Realistic 3D page flip with perspective, curl gradients, and cast shadows.
 */
export const realisticAnimator: FlipAnimator = {
  setup(root, state, direction, options, allPages) {
    const spread = root.querySelector('.ef-spread') as HTMLElement;
    if (!spread) return;

    const isForward = direction === 'forward';
    const wellClass = isForward ? '.ef-well-right' : '.ef-well-left';
    const well = spread.querySelector(wellClass) as HTMLElement;
    if (!well) return;

    const currentPage = well.querySelector('.ef-page:not([aria-hidden="true"])') as HTMLElement;
    if (!currentPage) return;

    // Find the BACK FACE page from the allPages array (not from DOM)
    const isSingle = state.isSinglePage;
    let backPageIdx: number;
    if (isForward) {
      // Forward: back of right page shows next spread's left page
      backPageIdx = isSingle ? state.currentPage + 1 : state.currentPage + 2;
    } else {
      // Backward: back of left page shows prev spread's right page
      backPageIdx = isSingle ? state.currentPage - 1 : state.currentPage - 1;
    }

    const backPageEl = allPages[backPageIdx] || null;

    // Create flip container
    const flipContainer = document.createElement('div');
    flipContainer.className = 'ef-flip-container';
    flipContainer.style.transformOrigin = isForward ? 'left center' : 'right center';
    flipContainer.style.transform = 'rotateY(0deg)';
    flipContainer.style.transition = 'none';

    // Front face (current page content)
    const front = document.createElement('div');
    front.className = 'ef-flip-front';
    const frontClone = currentPage.cloneNode(true) as HTMLElement;
    frontClone.style.position = 'relative';
    frontClone.style.display = '';
    frontClone.removeAttribute('aria-hidden');
    frontClone.removeAttribute('inert');
    front.appendChild(frontClone);

    // Back face (next page content — visible from the start of flip)
    const back = document.createElement('div');
    back.className = 'ef-flip-back';
    if (backPageEl) {
      const backClone = backPageEl.cloneNode(true) as HTMLElement;
      backClone.style.position = 'relative';
      backClone.style.display = '';
      backClone.removeAttribute('aria-hidden');
      backClone.removeAttribute('inert');
      back.appendChild(backClone);
    } else {
      // No next page — show white
      back.style.background = '#fff';
    }

    // Curl gradient overlay (on front face)
    const curlGrad = document.createElement('div');
    curlGrad.className = 'ef-curl-gradient';
    front.appendChild(curlGrad);

    // Is this a cover page? (hard flip, no curl)
    const isCover = options.show_cover && (
      state.currentPage === 0 ||
      state.currentPage >= state.pageCount - 2
    );

    flipContainer.appendChild(front);
    flipContainer.appendChild(back);

    // Shadow element on the underlying page
    const shadow = document.createElement('div');
    shadow.className = 'ef-cast-shadow';

    well.style.overflow = 'visible';
    well.appendChild(flipContainer);

    // Place shadow on the opposite well
    const oppositeWellClass = isForward ? '.ef-well-left' : '.ef-well-right';
    const oppositeWell = spread.querySelector(oppositeWellClass) as HTMLElement;
    if (oppositeWell) {
      oppositeWell.appendChild(shadow);
    }

    // Hide original page during animation
    currentPage.style.visibility = 'hidden';

    root.dataset.efFlipActive = '1';
    (root as any).__efFlip = { flipContainer, front, back, curlGrad, shadow, currentPage, well, oppositeWell, isCover, isForward };
  },

  apply(root, state, direction, progress, options) {
    const refs = (root as any).__efFlip;
    if (!refs) return;

    const { flipContainer, curlGrad, shadow, isCover, isForward } = refs;
    const maxShadow = options.max_shadow_opacity;

    // Rotation: 0 → -180 for forward, 0 → 180 for backward
    const angle = isForward ? -180 * progress : 180 * progress;
    flipContainer.style.transform = `rotateY(${angle}deg)`;

    // Curl gradient (not on covers)
    if (!isCover) {
      const intensity = Math.sin(progress * Math.PI);
      const gradientAngle = isForward ? '90deg' : '270deg';
      curlGrad.style.background = `linear-gradient(${gradientAngle}, `
        + `transparent 0%, `
        + `rgba(0,0,0,${0.08 * intensity}) 30%, `
        + `rgba(0,0,0,${0.15 * intensity}) 50%, `
        + `rgba(0,0,0,${0.05 * intensity}) 70%, `
        + `transparent 100%)`;
    } else {
      curlGrad.style.background = 'none';
    }

    // Cast shadow on underlying page
    const shadowIntensity = Math.sin(progress * Math.PI) * maxShadow;
    const shadowSpread = 20 + 30 * Math.sin(progress * Math.PI);
    shadow.style.background = isForward
      ? `linear-gradient(to right, rgba(0,0,0,${shadowIntensity}) 0%, transparent ${shadowSpread}%)`
      : `linear-gradient(to left, rgba(0,0,0,${shadowIntensity}) 0%, transparent ${shadowSpread}%)`;
  },

  cleanup(root) {
    const refs = (root as any).__efFlip;
    if (!refs) return;

    const { flipContainer, shadow, currentPage, well } = refs;
    currentPage.style.visibility = '';
    well.style.overflow = '';
    flipContainer.remove();
    shadow.remove();
    delete (root as any).__efFlip;
    delete root.dataset.efFlipActive;
  },
};
