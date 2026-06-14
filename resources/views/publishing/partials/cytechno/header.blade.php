{{-- Cytechno — sticky header with brand + nav --}}
@php
  $currentPath = $currentPath ?? '';
  $section = $currentSection ?? '';
@endphp
<header class="site-head">
  <div class="wrap bar">
    <a href="/" class="brand" aria-label="Cybertechnology — home">
      <span class="mark" aria-hidden="true"></span>
      <span class="wm">
        <b>Cyber Technology</b>
        <small>Secure · Scalable · Built to Last</small>
      </span>
    </a>

    <button class="burger" aria-label="Menu" onclick="this.nextElementSibling.classList.toggle('open')">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav" id="main-nav">
      <a href="/about"{{ $section === 'about' ? ' class=active' : '' }}>About</a>
      <a href="/services"{{ $section === 'services' ? ' class=active' : '' }}>Services</a>
      <a href="/portfolio"{{ $section === 'portfolio' ? ' class=active' : '' }}>Portfolio</a>
      <a href="/blog"{{ $section === 'blog' ? ' class=active' : '' }}>Blog</a>
      <a href="/ideas"{{ $section === 'ideas' ? ' class=active' : '' }}>Ideas</a>
      <a href="/products"{{ $section === 'products' ? ' class=active' : '' }}>Products</a>
      <a href="/contacts" class="navcta{{ $section === 'contacts' ? ' active' : '' }}">Contact</a>
    </nav>
  </div>
</header>
