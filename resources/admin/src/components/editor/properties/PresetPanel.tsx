import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Bookmark, Save, Loader2 } from 'lucide-react';
import { stylePresets, type StylePreset } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import type { BlockData, BlockStyleProps } from '@/types/blocks';

/**
 * Style Presets control (Builder Experience P3). Apply a named element preset to
 * this block (stored in data.__stylePreset — the block LINKS it, local settings
 * still override), or save the block's current style as a new reusable preset.
 */
export function PresetPanel({ block, onApply }: {
  block: BlockData;
  onApply: (presetId: string | null) => void;
}) {
  const { siteId = '' } = useParams();
  const qc = useQueryClient();
  const { toast } = useToast();
  const [saving, setSaving] = useState(false);

  const applied = (block.data as Record<string, unknown>)?.__stylePreset as string | undefined;

  const { data: presets = [] } = useQuery<StylePreset[]>({
    queryKey: ['style-presets', siteId, block.type],
    queryFn: () => stylePresets.list(siteId, block.type, 'element').then((r) => r.data.data),
  });

  const saveAsPreset = async () => {
    const style = (block.style || {}) as BlockStyleProps;
    if (!style || Object.keys(style).length === 0) {
      toast({ type: 'error', message: 'This block has no custom style to save yet.' });
      return;
    }
    const name = prompt('Preset name:');
    if (!name?.trim()) return;
    setSaving(true);
    try {
      const r = await stylePresets.create(siteId, {
        name: name.trim(), block_type: block.type, kind: 'element', style,
      });
      await qc.invalidateQueries({ queryKey: ['style-presets', siteId] });
      onApply(r.data.data.id); // link the block to the preset it was saved from
      toast({ type: 'success', message: `Saved “${name.trim()}” as a preset.` });
    } catch (e: any) {
      toast({ type: 'error', message: e?.response?.data?.message || 'Could not save the preset.' });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-2">
      <div>
        <label className="text-[10px] text-base-content/40 flex items-center gap-1 mb-1"><Bookmark size={10} /> Apply a preset</label>
        <select value={applied || ''} onChange={(e) => onApply(e.target.value || null)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="">— none (local styles only) —</option>
          {presets.map((p) => (
            <option key={p.id} value={p.id}>{p.name}{p.is_system ? ' · system' : ''}{p.is_default ? ' · default' : ''}</option>
          ))}
        </select>
      </div>
      {applied && (
        <p className="text-[10px] text-base-content/40 leading-snug">
          Linked to a preset — edit it under Style Presets to restyle every block that uses it. Local settings below still win.
        </p>
      )}
      <button onClick={saveAsPreset} disabled={saving}
        className="btn btn-outline btn-xs gap-1.5 w-full">
        {saving ? <Loader2 size={12} className="animate-spin" /> : <Save size={12} />}
        Save current style as preset
      </button>
    </div>
  );
}
