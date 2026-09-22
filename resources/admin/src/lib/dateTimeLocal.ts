/**
 * datetime-local ↔ ISO conversion (audit 2026-09-22, F29).
 *
 * `<input type="datetime-local">` holds a WALL-CLOCK time with no zone. The
 * editors used `new Date(iso).toISOString().slice(0, 16)` to fill it — the
 * UTC representation — and sent the raw input value back without an offset,
 * so a user in Europe/Sofia saw and stored times shifted by the zone offset
 * (2 or 3 hours depending on DST). These helpers convert explicitly, in the
 * browser's zone by default (a site/user zone can be passed).
 */

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

/** Wall-clock parts of an instant in a zone (Intl-based, DST-correct). */
function partsIn(date: Date, timeZone: string): { y: number; m: number; d: number; h: number; mi: number } {
  const f = new Intl.DateTimeFormat('en-US', {
    timeZone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
  });
  const get = (type: string) => Number(f.formatToParts(date).find((p) => p.type === type)?.value ?? 0);
  return { y: get('year'), m: get('month'), d: get('day'), h: get('hour') % 24, mi: get('minute') };
}

/** Offset (minutes east of UTC) of a zone at an instant. */
function offsetMinutes(date: Date, timeZone: string): number {
  const p = partsIn(date, timeZone);
  const asUtc = Date.UTC(p.y, p.m - 1, p.d, p.h, p.mi);
  return Math.round((asUtc - Math.floor(date.getTime() / 60000) * 60000) / 60000);
}

const browserZone = (): string => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

/** ISO/date → value for a datetime-local input ("YYYY-MM-DDTHH:MM") in the zone. */
export function toLocalInputValue(iso: string | null | undefined, timeZone: string = browserZone()): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const p = partsIn(date, timeZone);
  return `${p.y}-${pad(p.m)}-${pad(p.d)}T${pad(p.h)}:${pad(p.mi)}`;
}

/**
 * datetime-local value → ISO-8601 with the zone's offset (offset-aware, so
 * the server stores the instant the user meant). Empty → null. Non-existent
 * local times (spring-forward gap) resolve to the instant after the gap;
 * ambiguous ones (fall-back overlap) take the first (DST) occurrence — both
 * deterministic and documented.
 */
export function fromLocalInputValue(local: string | null | undefined, timeZone: string = browserZone()): string | null {
  if (!local) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(local);
  if (!m) return null;
  const [y, mo, d, h, mi] = [Number(m[1]), Number(m[2]), Number(m[3]), Number(m[4]), Number(m[5])];
  const wall = Date.UTC(y, mo - 1, d, h, mi);
  // Candidate instants from the offsets in force around this wall time; a
  // candidate is valid when the zone's offset at that instant reproduces it.
  const day = 86400000;
  const offA = offsetMinutes(new Date(wall), timeZone);
  const offB = offsetMinutes(new Date(wall - offA * 60000), timeZone);
  const offBefore = offsetMinutes(new Date(wall - day), timeZone); // offsets on either side of a transition
  const offAfter = offsetMinutes(new Date(wall + day), timeZone);
  const candidates = [...new Set([offA, offB, offBefore, offAfter])].map((o) => ({ off: o, instant: wall - o * 60000 }));
  const valid = candidates.filter((c) => offsetMinutes(new Date(c.instant), timeZone) === c.off);
  // Overlap (two valid): the larger offset is the first (DST) occurrence.
  // Gap (none valid): interpret with the larger offset too — the instant after the gap.
  const pick = (valid.length ? valid : candidates).sort((a, b) => b.off - a.off)[0];
  const off = pick.off;
  const instant = pick.instant;
  const sign = off >= 0 ? '+' : '-';
  const abs = Math.abs(off);
  const dt = new Date(instant + off * 60000);
  return `${dt.getUTCFullYear()}-${pad(dt.getUTCMonth() + 1)}-${pad(dt.getUTCDate())}T${pad(dt.getUTCHours())}:${pad(dt.getUTCMinutes())}:00${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`;
}
