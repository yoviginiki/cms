import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Plus, Trash2, GripVertical } from 'lucide-react';
import type { BlockEditorProps } from '@/types/blocks';
import { menus } from '@/lib/api';
import { TextField, SelectField, ToggleField, ColorField } from '@/components/editor/fields';

interface CustomItem {
  label: string;
  url: string;
  target: string;
}

export const MenuEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const { siteId = '' } = useParams();
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  const source = (data.source as string) || 'system';
  const customItems = (data.customItems as CustomItem[]) || [];

  // Load available menus for the system menu picker
  const { data: menuList, isError: menuListError } = useQuery({
    queryKey: ['menus-list', siteId],
    queryFn: () => menus.list(siteId).then(r => r.data.data),
    enabled: source === 'system' && !!siteId,
  });

  const addCustomItem = () => {
    update('customItems', [...customItems, { label: 'New Link', url: '#', target: '_self' }]);
  };

  const updateCustomItem = (idx: number, field: string, value: string) => {
    const items = [...customItems];
    items[idx] = { ...items[idx], [field]: value };
    update('customItems', items);
  };

  const removeCustomItem = (idx: number) => {
    update('customItems', customItems.filter((_, i) => i !== idx));
  };

  return (
    <div className="space-y-3">
      {/* ── Source ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Menu Source</div>
      <SelectField
        label="Source"
        value={source}
        onChange={(v) => update('source', v)}
        options={[
          { value: 'system', label: 'System Menu' },
          { value: 'custom', label: 'Custom Links' },
        ]}
        helperText="System Menu uses menus from the Menu Editor. Custom Links lets you create links directly here."
      />

      {/* System menu picker */}
      {source === 'system' && (
        <>
          <SelectField
            label="Select Menu"
            value={(data.menuId as string) || ''}
            onChange={(v) => update('menuId', v)}
            options={[
              { value: '', label: 'Primary (first menu)' },
              ...((menuList as Array<{ id: string; name: string }>) || []).map(m => ({
                value: m.id, label: m.name,
              })),
            ]}
            helperText={menuListError ? 'Failed to load menus. Check your connection.' : undefined}
          />
          {menuListError && (
            <p className="text-[10px] text-error">Could not load menus. The primary menu will be used as fallback.</p>
          )}
        </>
      )}

      {/* Custom items editor */}
      {source === 'custom' && (
        <div className="space-y-2">
          <label className="text-[11px] font-medium text-base-content/50">Custom Links</label>
          {customItems.map((item, i) => (
            <div key={i} className="flex items-center gap-1.5 bg-base-200/50 rounded-lg p-2">
              <GripVertical className="h-3 w-3 text-base-content/30 shrink-0" />
              <input
                value={item.label}
                onChange={(e) => updateCustomItem(i, 'label', e.target.value)}
                className="input input-bordered input-xs flex-1 text-[11px]"
                placeholder="Label"
              />
              <input
                value={item.url}
                onChange={(e) => updateCustomItem(i, 'url', e.target.value)}
                className="input input-bordered input-xs flex-1 text-[11px]"
                placeholder="URL or #anchor"
              />
              <select
                value={item.target}
                onChange={(e) => updateCustomItem(i, 'target', e.target.value)}
                className="select select-bordered select-xs text-[10px] w-20"
              >
                <option value="_self">Same</option>
                <option value="_blank">New</option>
              </select>
              <button onClick={() => removeCustomItem(i)} className="btn btn-ghost btn-xs p-1">
                <Trash2 className="h-3 w-3 text-error" />
              </button>
            </div>
          ))}
          <button onClick={addCustomItem} className="btn btn-ghost btn-sm btn-block text-[11px] border-dashed border-base-content/20">
            <Plus className="h-3 w-3" /> Add Link
          </button>
        </div>
      )}

      {/* ── Layout ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Layout</div>
      <SelectField
        label="Style"
        value={(data.style as string) || 'horizontal'}
        onChange={(v) => update('style', v)}
        options={[
          { value: 'horizontal', label: 'Horizontal' },
          { value: 'vertical', label: 'Vertical' },
          { value: 'hamburger', label: 'Hamburger Only' },
        ]}
      />
      <ToggleField label="Show Logo" value={data.showLogo === true} onChange={(v) => update('showLogo', v)} />
      <ToggleField label="Sticky on Scroll" value={data.sticky === true} onChange={(v) => update('sticky', v)} />
      <TextField
        label="Mobile Breakpoint"
        value={String(data.mobileBreakpoint ?? 768)}
        onChange={(v) => update('mobileBreakpoint', parseInt(v) || 768)}
        placeholder="768"
        helperText="Screen width (px) below which hamburger menu activates"
      />

      {/* ── Styling ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Styling</div>
      <ColorField label="Background Color" value={(data.bgColor as string) || ''} onChange={(v) => update('bgColor', v)} />
      <ColorField label="Text Color" value={(data.textColor as string) || ''} onChange={(v) => update('textColor', v)} />
      <ColorField label="Hover Color" value={(data.hoverColor as string) || ''} onChange={(v) => update('hoverColor', v)} />
      <ColorField label="Active Color" value={(data.activeColor as string) || ''} onChange={(v) => update('activeColor', v)} />
      <ColorField label="Border Color" value={(data.borderColor as string) || ''} onChange={(v) => update('borderColor', v)} />
      <TextField label="Font Size" value={(data.fontSize as string) || ''} onChange={(v) => update('fontSize', v)} placeholder="e.g. 0.875rem, 14px" />
      <SelectField
        label="Font Weight"
        value={(data.fontWeight as string) || ''}
        onChange={(v) => update('fontWeight', v)}
        options={[
          { value: '', label: 'Default' },
          { value: '400', label: 'Normal (400)' },
          { value: '500', label: 'Medium (500)' },
          { value: '600', label: 'Semibold (600)' },
          { value: '700', label: 'Bold (700)' },
        ]}
      />
      <TextField label="Padding" value={(data.padding as string) || ''} onChange={(v) => update('padding', v)} placeholder="e.g. 0.75rem 1.5rem" />
      <TextField label="Item Gap" value={(data.itemGap as string) || ''} onChange={(v) => update('itemGap', v)} placeholder="e.g. 1.5rem, 24px" />
      <TextField label="Border Radius" value={(data.borderRadius as string) || ''} onChange={(v) => update('borderRadius', v)} placeholder="e.g. 0.5rem, 8px" />
      <TextField label="Logo Size" value={(data.logoSize as string) || ''} onChange={(v) => update('logoSize', v)} placeholder="e.g. 1.5rem, 24px" />
    </div>
  );
};
