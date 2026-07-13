# Analytics

Two options, usable together: built-in privacy-light page analytics, and your own Google Analytics.

## Built-in analytics

Every published page carries a tiny beacon (a single non-blocking request — no cookies, no fingerprinting, no third parties). The **Analytics** page in the admin shows per site:

- **Page views** over time,
- **Top pages**,
- **Top referrers** — where visitors come from.

Because it's a beacon on your own domain, ad blockers rarely strip it, and there's nothing to consent-manage in most jurisdictions.

## Google Analytics

Paste your **GA measurement ID** in **Site Settings → Custom Code** and the standard gtag snippet is included on every published page. Use this when you need audiences, conversions, or campaign attribution beyond the built-in counters.

## Notes

- Neither option affects your PageSpeed score meaningfully: the beacon is fire-and-forget, and gtag loads async.
- The beacon posts to the platform domain; if you ever migrate the published site away from Stillopress hosting, the built-in counters stop while GA keeps working.
