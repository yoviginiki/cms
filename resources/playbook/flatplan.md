# Flatplanning — how to plan an issue

The flatplan is the issue seen from above: an ordered list of spreads, each with a job, a
pattern, and assigned material. Plan the whole issue before generating anything — rhythm
only exists across spreads.

## Sizing an issue from material

Count what the brief's inventory actually supports; never pad to a rounder page count.

Rules of thumb (A4 pages, body ≈ 450–550 words/page in a 2–3 column well):

- A text of N words fills roughly **N / 500 pages**, plus its opener spread.
- A feature = opener spread (2pp) + body pages + usually one `image-interruption` if it
  runs past 4pp and images allow.
- An interview of N words ≈ N / 450 pages (Q&A sets looser) + `portrait-profile` opener.
  It needs at least 1 usable portrait; with 3+ portraits add a `quote-beat`.
- Loose short texts (< 300 words each): 3–4 per FOB spread (`fob-stack`) or 5–7 per
  `item-mosaic`.
- Images without text: they are openers, interruptions, plates, or grids — an image-only
  feature (`artwork-plate` / `image-grid-quartet` sequence) needs 4+ related images.
- Fixed overhead: cover (1p) + `closer-colophon` (final spread).

Then round DOWN to what the material genuinely fills. A tight 12-page issue beats a
padded 20. Issues are sized in whole spreads; total page count = 1 (cover) + 2 × spreads.

If the user states a page ambition the material can't fill, plan the smaller honest size
and say so in one sentence — never stretch content thin.

## Slotting logic

1. **Cover** — pick the single strongest image in inventory (`cover-image`); if none is
   strong, `cover-type`.
2. **FOB** — all short items, grouped by kind: newsy shorts → `fob-stack`; abundant
   lifestyle items → `item-mosaic`. 1–2 spreads for issues under 24pp.
3. **Feature well** — every long text and interview, each with an opener. Front-of-book
   never interrupts the well.
4. **BOB** — the one reflective essay (`quiet-single-column`), service pieces
   (`how-to-object`), leftovers that earn their place, then `closer-colophon`.

What makes something a feature vs. FOB: length (800+ words), image support, and whether
it carries an idea (features) vs. an update (FOB).

## Sequencing the well

- **Strongest second**: open the well with a good feature, place the best one after it.
  The reader's trust peaks after the first feature delivers; spend the masterpiece there.
- Taper: remaining features in descending strength, but alternate register (after a heavy
  politics feature, a visual or human piece) so the taper reads as variety, not decline.
- End the issue on a quiet note: the last content spread before the closer should be
  quiet or medium weight — never a loud spread crashing into the colophon.

## Rhythm check (do this before emitting)

Read the planned spreads as a weight strip (each pattern's weight is defined in
spread-patterns.md). Fix violations by swapping patterns or inserting/removing a spread:

- No three louds in a row; no three quiets in a row.
- Every loud is followed within one spread by a quiet or medium.
- The well contains the issue's 2–3 loudest spreads; FOB and BOB stay medium and under.

## Defaults ladder (ease-of-use doctrine)

The user gives as little as they give. Fill gaps top-down, decide silently, and record
each assumption in the spread's `intent` line rather than asking:

1. No genre stated → infer from the material's subject; if genuinely mixed, use
   `lifestyle` structure with `universal.md` restraint.
2. No page ambition → size purely from material (rules above).
3. No title → propose a working title from the strongest text's theme (user can rename
   at any point; never block on it).
4. Sparse images → type-led patterns (`poster-type-opener`, `stat-punch`,
   `text-well-*`); never invent or fetch images.
5. One text only → a single-feature issue is legitimate: cover, opener, body well with
   interruptions, closer. Say what it is, don't apologize for it.
6. Material with no home → leave it out and note the omission in one line. An issue is
   an edit, not a container.

## Flatplan output contract

The generator emits strict JSON — one object per spread, ordered. Fields (the
authoritative JSON Schema lives in code; keep this list in sync):

- `position` — integer, 0 = cover, 1..n = spreads in reading order.
- `working_title` — short editorial title for the slot.
- `section` — `cover` | `fob` | `feature` | `bob`.
- `pattern` — one name from spread-patterns.md (covers: `cover-image` | `cover-type`).
- `materials` — array of material ids from the brief inventory assigned to this spread
  (may be empty only for `quiet-single-column` continuations and `closer-colophon`).
- `intent` — one sentence: what this spread says, including any silent assumption made.

Validation is hard: unknown pattern names, unresolvable material ids, or a missing cover
/ closer are rejected and regenerated with the validation errors attached.
