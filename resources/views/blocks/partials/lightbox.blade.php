{{-- Shared image lightbox (gallery, linear-gallery). Rendered once per page; opens for any <a data-glb-item> inside a [data-lightbox="1"] container. --}}
@once('gallery-lightbox')
<style>
.glb{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.94);display:none;flex-direction:column;align-items:center;justify-content:center;color:#fff;font-family:inherit;-webkit-user-select:none;user-select:none}
.glb.is-open{display:flex}
.glb-stage{position:relative;display:flex;align-items:center;justify-content:center;width:100%;flex:1;min-height:0;padding:56px 64px 16px;box-sizing:border-box}
.glb-img{max-width:100%;max-height:100%;object-fit:contain;box-shadow:0 10px 40px rgba(0,0,0,.6);opacity:0;transition:opacity .2s ease}
.glb-img.is-ready{opacity:1}
.glb-cap{padding:8px 64px 20px;text-align:center;font-size:.9rem;color:rgba(255,255,255,.85);max-width:90vw;min-height:1.5em}
.glb-btn{position:absolute;background:rgba(255,255,255,.08);border:0;color:#fff;cursor:pointer;border-radius:999px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;transition:background .2s}
.glb-btn:hover,.glb-btn:focus-visible{background:rgba(255,255,255,.22);outline:none}
.glb-btn svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.glb-close{top:12px;right:12px}
.glb-prev{left:12px;top:50%;transform:translateY(-50%)}
.glb-next{right:12px;top:50%;transform:translateY(-50%)}
.glb-count{position:absolute;top:22px;left:16px;font-size:.8rem;color:rgba(255,255,255,.6);letter-spacing:.05em}
.glb-spin{position:absolute;width:28px;height:28px;border:3px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:glbspin .8s linear infinite;display:none}
.glb.is-loading .glb-spin{display:block}
@keyframes glbspin{to{transform:rotate(360deg)}}
@media(max-width:640px){.glb-stage{padding:56px 8px 8px}.glb-prev,.glb-next{top:auto;bottom:12px;transform:none}.glb-prev{left:12px}.glb-next{right:12px}.glb-cap{padding:8px 16px 64px}}
@media(prefers-reduced-motion:reduce){.glb-img{transition:none}}
</style>
<div class="glb" id="glb" role="dialog" aria-modal="true" aria-label="Image viewer" hidden>
  <span class="glb-count" id="glb-count"></span>
  <button type="button" class="glb-btn glb-close" data-glb="close" aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
  <div class="glb-stage">
    <button type="button" class="glb-btn glb-prev" data-glb="prev" aria-label="Previous image"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button>
    <div class="glb-spin"></div>
    <img class="glb-img" id="glb-img" src="" alt="">
    <button type="button" class="glb-btn glb-next" data-glb="next" aria-label="Next image"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button>
  </div>
  <div class="glb-cap" id="glb-cap"></div>
</div>
<script>
(function(){
  // Blocks are rendered one by one at publish time, so Blade's once-directive cannot
  // dedupe across blocks: drop any duplicate overlay and bind only once.
  var all=document.querySelectorAll('.glb');for(var i=1;i<all.length;i++){all[i].parentNode.removeChild(all[i]);}
  if(window.__glbInit)return;window.__glbInit=true;
  var box=document.getElementById('glb'),img=document.getElementById('glb-img'),cap=document.getElementById('glb-cap'),cnt=document.getElementById('glb-count');
  if(!box)return;
  var links=[],idx=0,lastFocus=null,touchX=null;
  function show(n){
    if(!links.length)return;
    idx=(n+links.length)%links.length;
    var a=links[idx],full=a.getAttribute('href'),im=a.querySelector('img');
    box.classList.add('is-loading');img.classList.remove('is-ready');
    img.alt=(im&&im.getAttribute('alt'))||'';
    img.src=full;
    cap.textContent=a.getAttribute('data-caption')||'';
    cnt.textContent=links.length>1?(idx+1)+' / '+links.length:'';
    box.querySelector('.glb-prev').style.visibility=links.length>1?'':'hidden';
    box.querySelector('.glb-next').style.visibility=links.length>1?'':'hidden';
    var nx=links[(idx+1)%links.length],pv=links[(idx-1+links.length)%links.length];
    [nx,pv].forEach(function(l){if(l&&l!==a){var p=new Image();p.src=l.getAttribute('href');}});
  }
  img.addEventListener('load',function(){box.classList.remove('is-loading');img.classList.add('is-ready');});
  img.addEventListener('error',function(){box.classList.remove('is-loading');img.classList.add('is-ready');});
  function open(gallery,link){
    links=Array.prototype.slice.call(gallery.querySelectorAll('a[data-glb-item]'));
    lastFocus=document.activeElement;
    box.hidden=false;box.classList.add('is-open');document.body.style.overflow='hidden';
    show(Math.max(0,links.indexOf(link)));
    box.querySelector('.glb-close').focus();
  }
  function close(){
    box.classList.remove('is-open');box.hidden=true;document.body.style.overflow='';img.src='';
    if(lastFocus&&lastFocus.focus)lastFocus.focus();
  }
  var ptr='mouse';
  document.addEventListener('pointerdown',function(e){ptr=e.pointerType||'mouse';},{capture:true,passive:true});
  function wantsDbl(g){return g.getAttribute('data-open')==='dblclick'&&ptr!=='touch';}
  document.addEventListener('dblclick',function(e){
    var a=e.target.closest('a[data-glb-item]');if(!a)return;
    var g=a.closest('[data-lightbox="1"]');if(!g||!wantsDbl(g))return;
    e.preventDefault();open(g,a);
  });
  document.addEventListener('click',function(e){
    var ctl=e.target.closest('[data-glb]');
    if(ctl&&box.contains(ctl)){e.preventDefault();var k=ctl.getAttribute('data-glb');if(k==='close')close();else if(k==='prev')show(idx-1);else show(idx+1);return;}
    if(box.classList.contains('is-open')&&e.target===box.querySelector('.glb-stage')){close();return;}
    var a=e.target.closest('a[data-glb-item]');
    if(!a)return;
    var g=a.closest('[data-lightbox="1"]');
    if(!g)return;
    if(e.metaKey||e.ctrlKey||e.shiftKey||e.button===1)return;
    e.preventDefault();
    if(wantsDbl(g))return; // single click just grabs/selects; double-click opens
    open(g,a);
  });
  document.addEventListener('keydown',function(e){
    if(!box.classList.contains('is-open'))return;
    if(e.key==='Escape'){close();}
    else if(e.key==='ArrowRight'){show(idx+1);}
    else if(e.key==='ArrowLeft'){show(idx-1);}
    else if(e.key==='Tab'){var f=box.querySelectorAll('button');var first=f[0],last=f[f.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}
  });
  box.addEventListener('touchstart',function(e){touchX=e.touches[0].clientX;},{passive:true});
  box.addEventListener('touchend',function(e){if(touchX===null)return;var dx=e.changedTouches[0].clientX-touchX;touchX=null;if(Math.abs(dx)>40){show(dx<0?idx+1:idx-1);}},{passive:true});
})();
</script>
@endonce
