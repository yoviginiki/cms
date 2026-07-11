import { useQuery } from '@tanstack/react-query';
import { stylePresets, type StylePreset } from '@/lib/api';
import type { BlockData, BlockStyleProps } from '@/types/blocks';

/**
 * Frontend mirror of app/Domain/Blocks/Services/StylePresetResolver (P3), so the
 * editor CANVAS reflects an applied preset exactly like the published output.
 * A block LINKS presets via data.__stylePreset (element) + data.__presetGroups
 * (stackable groups); resolution order is element → groups → local (local wins).
 * Token refs ($color.accent) stay as-is — blockStyles.ts safeColor/safeDim
 * compile them, matching the PHP sanitizers.
 */
function deepMerge(base: any, over: any): any {
  const out: any = { ...base };
  for (const k in over) {
    const v = over[k];
    if (v && typeof v === 'object' && !Array.isArray(v) && out[k] && typeof out[k] === 'object' && !Array.isArray(out[k])) {
      out[k] = deepMerge(out[k], v);
    } else {
      out[k] = v;
    }
  }
  return out;
}

/** Effective style for a block = its linked presets merged under its local style. */
export function resolvePresetStyle(block: BlockData, presets: StylePreset[]): BlockStyleProps {
  const local = (block.style ?? {}) as BlockStyleProps;
  const data = block.data as Record<string, unknown> | undefined;
  const elementId = typeof data?.__stylePreset === 'string' ? (data.__stylePreset as string) : undefined;
  const groupIds = Array.isArray(data?.__presetGroups) ? (data!.__presetGroups as string[]) : [];
  const ids = [elementId, ...groupIds].filter((x): x is string => typeof x === 'string' && x !== '');
  if (ids.length === 0) return local; // no preset — unchanged (same ref, no re-render churn)

  const byId = new Map(presets.map((p) => [p.id, p]));
  let merged: any = {};
  for (const id of ids) {
    const p = byId.get(id);
    if (p?.style) merged = deepMerge(merged, p.style);
  }
  return deepMerge(merged, local) as BlockStyleProps; // local overrides win
}

/** Cached list of the site's style presets (shared across all canvas blocks). */
export function useStylePresets(siteId: string): StylePreset[] {
  const { data } = useQuery<StylePreset[]>({
    queryKey: ['style-presets', siteId, 'all'],
    queryFn: () => stylePresets.list(siteId).then((r) => r.data.data),
    enabled: !!siteId,
    staleTime: 30_000,
  });
  return data ?? [];
}
