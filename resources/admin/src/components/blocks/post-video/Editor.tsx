import type { BlockEditorProps } from '@/types/blocks';
import { SelectField, ToggleField } from '@/components/editor/fields';

export const PostVideoEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <div className="bg-indigo-50 text-indigo-700 text-xs p-2 rounded">
        Embeds the video from the post's video URL field. The source is resolved dynamically at render time.
      </div>
      <SelectField label="Aspect Ratio" value={(data.aspectRatio as string) || '16:9'} onChange={v => update('aspectRatio', v)}
        options={[
          { value: '16:9', label: '16:9' },
          { value: '4:3', label: '4:3' },
          { value: '1:1', label: '1:1' },
        ]} />
      <ToggleField label="Autoplay" value={!!data.autoplay} onChange={v => update('autoplay', v)} />
      <ToggleField label="Show Controls" value={data.controls !== false} onChange={v => update('controls', v)} />
    </div>
  );
};
