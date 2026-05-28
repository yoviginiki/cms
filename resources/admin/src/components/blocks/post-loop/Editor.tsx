import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, TextField, ToggleField } from '@/components/editor/fields';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const PostLoopEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Renders posts from the current archive/category
      </div>
      <SelectField label="Layout" value={(data.layout as string) || 'cards'} onChange={v => update('layout', v)}
        options={[
          { value: 'cards', label: 'Cards (grid)' },
          { value: 'list', label: 'List (rows)' },
          { value: 'grid', label: 'Masonry Grid' },
          { value: 'featured', label: 'Featured + List' },
        ]} />
      <SelectField label="Columns" value={String(data.columns ?? 3)} onChange={v => update('columns', Number(v))}
        options={[{ value: '1', label: '1' }, { value: '2', label: '2' }, { value: '3', label: '3' }, { value: '4', label: '4' }]} />
      <TextField label="Limit" value={String(data.limit ?? 12)} onChange={v => update('limit', Number(v))} placeholder="12" />
      <TextField label="Gap" value={(data.gap as string) || ''} onChange={v => update('gap', v)} placeholder="1.5rem" />
      <div className="divider text-[10px] text-base-content/40 my-1">Card Options</div>
      <ToggleField label="Show Image" value={data.showImage !== false} onChange={v => update('showImage', v)} />
      <ToggleField label="Show Excerpt" value={data.showExcerpt !== false} onChange={v => update('showExcerpt', v)} />
      <ToggleField label="Show Date" value={data.showDate !== false} onChange={v => update('showDate', v)} />
      <ToggleField label="Show Author" value={!!data.showAuthor} onChange={v => update('showAuthor', v)} />
      <ToggleField label="Show Category" value={!!data.showCategory} onChange={v => update('showCategory', v)} />
      <SelectField label="Image Aspect Ratio" value={(data.imageAspectRatio as string) || '16:9'} onChange={v => update('imageAspectRatio', v)}
        options={[
          { value: '16:9', label: '16:9' }, { value: '4:3', label: '4:3' },
          { value: '1:1', label: 'Square' }, { value: '3:2', label: '3:2' },
        ]} />
      <TextField label="Excerpt Lines" value={String(data.excerptLines ?? 3)} onChange={v => update('excerptLines', Number(v))} placeholder="3" />
      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(block.data as any).effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>
    </div>
  );
};
