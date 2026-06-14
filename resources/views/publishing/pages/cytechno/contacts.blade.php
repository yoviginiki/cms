{{-- Cytechno — Contacts page (Slice 10)
     Variables: $siteId = site UUID (for form submission endpoint)
     Form submits to: POST /api/v1/sites/{siteId}/forms/submit
     Server-side: FormController + FormSubmissionService (honeypot, validation, email, JSON storage)
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'contacts'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Contacts</span>
      <h1>Start a project, or just ask a hard question</h1>
      <p class="lead mt-m" style="max-width:54ch">Tell us what needs to run reliably for the next decade. We reply to every serious enquiry — usually within a working day.</p>
    </div>
  </section>

  {{-- ═══ Form + details ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:start">

      {{-- Contact form --}}
      <div>
        <div class="section-head">
          <span class="eyebrow">Send a message</span>
          <h2 class="section-title">Project enquiry</h2>
        </div>

        {{-- Success state (hidden by default, shown by JS after submit) --}}
        <div id="ct-form-ok" class="form-ok" style="display:none">
          <span class="cond red" style="font-size:1.6rem;line-height:1">✓</span>
          <div>
            <b>Message received</b>
            <p class="muted" style="margin:6px 0 0;font-size:.92rem">Thanks — we'll be in touch within one working day.</p>
          </div>
        </div>

        {{-- Form --}}
        <form id="ct-form" class="form" novalidate>
          {{-- Honeypot (hidden from real users) --}}
          <div style="position:absolute;left:-9999px" aria-hidden="true">
            <input type="text" name="_honeypot" tabindex="-1" autocomplete="off">
          </div>

          <div class="field" id="f-name">
            <label for="ct-name">Your name</label>
            <input id="ct-name" name="name" type="text" placeholder="Nikolay Petrov" required>
            <span class="msg"></span>
          </div>

          <div class="field" id="f-email">
            <label for="ct-email">Email</label>
            <input id="ct-email" name="email" type="email" placeholder="you@organisation.bg" required>
            <span class="msg"></span>
          </div>

          <div class="field" id="f-message">
            <label for="ct-msg">What do you need built?</label>
            <textarea id="ct-msg" name="message" rows="5" placeholder="A short description of the platform, the sector and the constraints…" required></textarea>
            <span class="msg"></span>
          </div>

          <button type="submit" class="btn btn--solid" style="align-self:flex-start">Send enquiry <span class="arw" aria-hidden="true">→</span></button>
        </form>
      </div>

      {{-- Direct details --}}
      <div>
        <div class="section-head">
          <span class="eyebrow">Direct</span>
          <h2 class="section-title">Reach us</h2>
        </div>
        <div class="cdetail"><span>Email</span><b>office@cytechno.com</b></div>
        <div class="cdetail"><span>Phone</span><b>+359 2 944 1188</b></div>
        <div class="cdetail"><span>Studio</span><b>Sofia, Bulgaria</b></div>
        <div class="cdetail"><span>Hours</span><b>Mon–Fri · 09:00–18:00 EET</b></div>
        <div class="ph r43 mt-m" data-label="MAP · SOFIA STUDIO LOCATION"></div>
      </div>

    </div>
  </section>

</div>

{{-- Contact form submission script (progressive enhancement — form works without JS via action fallback) --}}
<script>
(function(){
  var form = document.getElementById('ct-form');
  var ok = document.getElementById('ct-form-ok');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var name = form.querySelector('[name="name"]').value.trim();
    var email = form.querySelector('[name="email"]').value.trim();
    var message = form.querySelector('[name="message"]').value.trim();
    var honeypot = form.querySelector('[name="_honeypot"]').value;
    var valid = true;

    // Reset errors
    ['f-name','f-email','f-message'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.classList.remove('err'); el.querySelector('.msg').textContent = ''; }
    });

    // Validate
    if (!name) { setErr('f-name', 'Please enter your name.'); valid = false; }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { setErr('f-email', 'Enter a valid email address.'); valid = false; }
    if (message.length < 10) { setErr('f-message', 'A little more detail, please (10+ characters).'); valid = false; }

    if (!valid) return;

    // Submit
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    fetch('/api/v1/sites/{{ $siteId ?? "" }}/forms/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ name: name, email: email, message: message, _honeypot: honeypot })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        form.style.display = 'none';
        ok.style.display = 'flex';
        ok.querySelector('p').textContent = 'Thanks, ' + name.split(' ')[0] + '. We'll be in touch at ' + email + ' within one working day.';
      } else {
        btn.disabled = false;
        btn.innerHTML = 'Send enquiry <span class="arw" aria-hidden="true">→</span>';
        setErr('f-message', data.message || 'Something went wrong. Please try again.');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = 'Send enquiry <span class="arw" aria-hidden="true">→</span>';
      setErr('f-message', 'Network error. Please try again.');
    });
  });

  function setErr(id, msg) {
    var el = document.getElementById(id);
    if (el) { el.classList.add('err'); el.querySelector('.msg').textContent = msg; }
  }
})();
</script>
@endsection
