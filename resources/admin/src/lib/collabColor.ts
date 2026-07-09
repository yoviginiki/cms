// Deterministic per-user color (stable across clients) for presence avatars and
// live cursors — so a given editor is always the same hue everywhere. Client-side
// so no md5 dependency; the same id always maps to the same color.
export function colorForId(id: string): string {
  let h = 0;
  for (let i = 0; i < id.length; i++) h = (h * 31 + id.charCodeAt(i)) % 360;
  return `hsl(${h}, 70%, 45%)`;
}
