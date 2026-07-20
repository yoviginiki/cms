# Third-Party Licenses

## GSAP (GreenSock Animation Platform)
- Version: 3.15.0
- License: Standard "No Charge" License (https://gsap.com/standard-license)
- NOT MIT — free for commercial use but not open source
- Used for: Experience Mode panel-to-panel snap navigation (Observer + ScrollTrigger);
  Slider block scene timelines (GSAP CORE ONLY — no SplitText or other paid plugins;
  text splitting uses a custom split() helper in resources/js/motion-runtime.js)
- Bundled in: public/assets/experience/experience-runtime.js (only on cinematic pages);
  SELF-HOSTED at /assets/vendor/gsap-3.15.0.min.js alongside the hashed
  motion-runtime file on pages embedding a slider (tenant CSPs block CDNs)

## Other Libraries (MIT)
- Swiper — MIT License — image carousels
- Lottie (lottie-web) — MIT License — animated loaders (optional, future)
- openspout/openspout ^4.30 — MIT License — streaming CSV/XLSX read/write for
  collection imports/exports (server-side only, not bundled in published output)
