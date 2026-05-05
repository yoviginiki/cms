{{-- Gallery: image-heavy with lightbox --}}
@include('layouts.partials.site-header')

<main style="padding:1rem;">
    {!! $blocksHtml !!}
</main>

{{-- Lightbox overlay --}}
<div id="gallery-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.95);z-index:9999;align-items:center;justify-content:center;">
    <button onclick="closeLightbox()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;z-index:10;">×</button>
    <button onclick="lightboxPrev()" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;">‹</button>
    <img id="gallery-lightbox-img" style="max-width:90vw;max-height:90vh;object-fit:contain;" />
    <button onclick="lightboxNext()" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;">›</button>
</div>

@include('layouts.partials.site-footer')

<script>
var lbImages = [], lbIdx = 0;
document.querySelectorAll('main img').forEach(function(img, i) {
    lbImages.push(img.src);
    img.style.cursor = 'pointer';
    img.onclick = function() { openLightbox(i); };
});
function openLightbox(i) {
    lbIdx = i;
    var lb = document.getElementById('gallery-lightbox');
    lb.style.display = 'flex';
    document.getElementById('gallery-lightbox-img').src = lbImages[i];
    document.addEventListener('keydown', lbKeyHandler);
}
function closeLightbox() {
    document.getElementById('gallery-lightbox').style.display = 'none';
    document.removeEventListener('keydown', lbKeyHandler);
}
function lightboxPrev() { lbIdx = (lbIdx - 1 + lbImages.length) % lbImages.length; document.getElementById('gallery-lightbox-img').src = lbImages[lbIdx]; }
function lightboxNext() { lbIdx = (lbIdx + 1) % lbImages.length; document.getElementById('gallery-lightbox-img').src = lbImages[lbIdx]; }
function lbKeyHandler(e) {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxPrev();
    if (e.key === 'ArrowRight') lightboxNext();
}
</script>
