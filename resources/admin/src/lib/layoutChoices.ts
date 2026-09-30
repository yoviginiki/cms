// The page/post "Layout" picker offers exactly three choices:
//   Standard (value "") — the site's grid: header, menus, areas, footer
//   Bare     — only the page/post content: no menu, no footer, nothing else
//   Landing  — a predefined landing wrapper
// Other historic system layouts (full-bleed, longform, gallery, …) are no
// longer offered; content that already uses one keeps it and shows it as
// "(legacy)" so nothing changes silently.
export const PICKABLE_LAYOUT_SLUGS = ['bare', 'landing'];

export function pickableLayouts<T extends { id: string; slug: string; name: string }>(
  all: T[] | undefined,
  currentId?: string | null,
): Array<T & { legacy?: boolean }> {
  const list = all ?? [];
  const pick = list.filter(l => PICKABLE_LAYOUT_SLUGS.includes(l.slug));
  const current = currentId ? list.find(l => l.id === currentId) : undefined;
  if (current && current.slug !== 'standard' && !pick.includes(current)) {
    return [...pick, { ...current, legacy: true }];
  }
  return pick;
}
