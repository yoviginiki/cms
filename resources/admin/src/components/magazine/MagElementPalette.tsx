import { useState } from 'react';
import { Search } from 'lucide-react';
// types imported for future use
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';

interface ElementDef {
  type: string;
  label: string;
  description: string;
  width: number;
  height: number;
  isBlock?: boolean;
}

interface CategoryGroup {
  label: string;
  items: ElementDef[];
}

const MAGAZINE_ELEMENTS: CategoryGroup[] = [
  {
    label: 'Text frames',
    items: [
      { type: 'text_frame', label: 'Text frame', description: 'General text container with rich formatting', width: 300, height: 150 },
      { type: 'headline_frame', label: 'Headline', description: 'Large display text for titles', width: 400, height: 80 },
      { type: 'pullquote_frame', label: 'Pull quote', description: 'Decorative quote pulled from text', width: 300, height: 120 },
      { type: 'caption_frame', label: 'Caption', description: 'Small text for image captions', width: 250, height: 40 },
      { type: 'footnote_frame', label: 'Footnote', description: 'Reference note at bottom of page', width: 300, height: 30 },
      { type: 'marginalia_frame', label: 'Marginalia', description: 'Side note in the margin area', width: 150, height: 100 },
    ],
  },
  {
    label: 'Image frames',
    items: [
      { type: 'image_frame', label: 'Image frame', description: 'Rectangular image container', width: 300, height: 200 },
      { type: 'circular_image', label: 'Circular image', description: 'Round clipped image (avatars)', width: 150, height: 150 },
      { type: 'polygon_image', label: 'Polygon image', description: 'Custom shaped image clip', width: 200, height: 200 },
      { type: 'fullbleed_image', label: 'Full-bleed image', description: 'Image extending to page edges', width: 595, height: 400 },
      { type: 'gallery_frame', label: 'Gallery', description: 'Grid of multiple images', width: 400, height: 300 },
      { type: 'background_image', label: 'Background image', description: 'Full-page background', width: 595, height: 842 },
    ],
  },
  {
    label: 'Shapes',
    items: [
      { type: 'rectangle', label: 'Rectangle', description: 'Box shape with fill and stroke', width: 200, height: 120 },
      { type: 'ellipse', label: 'Ellipse', description: 'Circle or oval shape', width: 150, height: 150 },
      { type: 'line', label: 'Line', description: 'Straight line with arrows', width: 300, height: 2 },
      { type: 'polygon', label: 'Polygon', description: 'Triangle, pentagon, hexagon, star', width: 150, height: 150 },
      { type: 'freeform_path', label: 'Freeform path', description: 'Custom drawn SVG path', width: 200, height: 100 },
      { type: 'decorative_rule', label: 'Decorative rule', description: 'Ornamental divider line', width: 400, height: 20 },
      { type: 'gradient_overlay', label: 'Gradient overlay', description: 'Color gradient shape', width: 300, height: 200 },
    ],
  },
  {
    label: 'Media',
    items: [
      { type: 'video_frame', label: 'Video', description: 'Embedded YouTube/Vimeo video', width: 400, height: 225 },
      { type: 'audio_player', label: 'Audio player', description: 'Embedded audio with controls', width: 300, height: 60 },
      { type: 'embed_frame', label: 'Embed', description: 'Custom HTML or iframe embed', width: 400, height: 300 },
      { type: 'svg_icon', label: 'Icon', description: 'SVG icon from icon library', width: 48, height: 48 },
    ],
  },
  {
    label: 'Interactive',
    items: [
      { type: 'button', label: 'Button', description: 'Clickable CTA button', width: 200, height: 50 },
      { type: 'hotspot', label: 'Hotspot', description: 'Invisible clickable area', width: 100, height: 100 },
      { type: 'tooltip_trigger', label: 'Tooltip', description: 'Hover to show tooltip text', width: 100, height: 30 },
      { type: 'accordion_frame', label: 'Accordion', description: 'Expandable content sections', width: 400, height: 200 },
      { type: 'slidein_panel', label: 'Slide-in panel', description: 'Content that slides in on click', width: 300, height: 400 },
    ],
  },
  {
    label: 'Data',
    items: [
      { type: 'table_frame', label: 'Table', description: 'Data table with rows and columns', width: 400, height: 200 },
      { type: 'chart_frame', label: 'Chart', description: 'Bar, line, pie or donut chart', width: 300, height: 250 },
      { type: 'infographic_number', label: 'Stat number', description: 'Large animated number with label', width: 150, height: 80 },
      { type: 'progress_indicator', label: 'Progress bar', description: 'Visual progress indicator', width: 300, height: 30 },
    ],
  },
  {
    label: 'Page structure',
    items: [
      { type: 'page_number', label: 'Page number', description: 'Auto-incrementing page number', width: 50, height: 20 },
      { type: 'running_header', label: 'Running header', description: 'Repeated text in header area', width: 400, height: 30 },
      { type: 'column_guides', label: 'Column guides', description: 'Visual column grid overlay', width: 595, height: 842 },
    ],
  },
  {
    label: 'Grouping',
    items: [
      { type: 'group', label: 'Group', description: 'Container to group elements together', width: 300, height: 200 },
      { type: 'component_instance', label: 'Component', description: 'Reusable saved component', width: 300, height: 200 },
      { type: 'clipping_group', label: 'Clipping group', description: 'Group with clipping mask', width: 300, height: 200 },
    ],
  },
];

interface Props {
  onAddElement: (type: string, x: number, y: number, w: number, h: number) => void;
}

export default function MagElementPalette({ onAddElement }: Props) {
  const [search, setSearch] = useState('');
  const [tab, setTab] = useState<'elements' | 'blocks'>('elements');

  // Filter elements by search
  const filteredGroups = MAGAZINE_ELEMENTS.map(g => ({
    ...g,
    items: g.items.filter(i =>
      !search || i.label.toLowerCase().includes(search.toLowerCase()) || i.description.toLowerCase().includes(search.toLowerCase())
    ),
  })).filter(g => g.items.length > 0);

  // Get blocks from block registry
  const blockGroups = (() => {
    const all = blockRegistry.getAll();
    const groups = new Map<string, Array<{ type: string; label: string }>>();
    for (const [, reg] of all) {
      const cat = reg.definition.category;
      if (!groups.has(cat)) groups.set(cat, []);
      const item = { type: reg.definition.type, label: reg.definition.label };
      if (!search || item.label.toLowerCase().includes(search.toLowerCase()) || item.type.includes(search.toLowerCase())) {
        groups.get(cat)!.push(item);
      }
    }
    return groups;
  })();

  const handleAddMagElement = (el: ElementDef) => {
    // Add at center of visible area with default dimensions
    const x = 50;
    const y = 100;
    onAddElement(el.type, x, y, el.width, el.height);
  };

  const handleAddBlock = (blockType: string) => {
    // Blocks get wrapped as text_frame elements with block content
    const reg = blockRegistry.get(blockType);
    if (!reg) return;
    onAddElement('text_frame', 50, 100, 300, 150);
  };

  return (
    <div className="flex flex-col h-full">
      {/* Tab switcher */}
      <div className="flex border-b border-base-300/20 shrink-0">
        <button onClick={() => setTab('elements')}
          className={`flex-1 px-2 py-2 text-[11px] font-medium ${tab === 'elements' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
          Elements ({MAGAZINE_ELEMENTS.reduce((n, g) => n + g.items.length, 0)})
        </button>
        <button onClick={() => setTab('blocks')}
          className={`flex-1 px-2 py-2 text-[11px] font-medium ${tab === 'blocks' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
          Blocks ({blockRegistry.getAll().size})
        </button>
      </div>

      {/* Search */}
      <div className="p-2 border-b border-base-300/10">
        <label className="input input-bordered input-xs flex items-center gap-2 text-[11px]">
          <Search className="h-3 w-3 text-base-content/30" />
          <input name="mag-magelementpalette-1" type="text" value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Search..." className="grow bg-transparent" />
        </label>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto p-2 space-y-3">
        {tab === 'elements' && (
          <>
            {filteredGroups.map(group => (
              <div key={group.label}>
                <div className="text-[9px] font-medium uppercase tracking-wider text-base-content/25 mb-1 px-1">
                  {group.label} ({group.items.length})
                </div>
                <div className="space-y-0">
                  {group.items.map(item => (
                    <button key={item.type} onClick={() => handleAddMagElement(item)}
                      className="w-full flex items-start gap-2 px-2 py-1.5 rounded text-left hover:bg-base-300/15 transition-colors group">
                      <div className="min-w-0 flex-1">
                        <div className="text-[11px] text-base-content/70 group-hover:text-base-content/90">{item.label}</div>
                        <div className="text-[9px] text-base-content/30 leading-tight mt-0.5">{item.description}</div>
                      </div>
                    </button>
                  ))}
                </div>
              </div>
            ))}

            {filteredGroups.length === 0 && (
              <div className="text-center py-8 text-[11px] text-base-content/25">No elements match "{search}"</div>
            )}
          </>
        )}

        {tab === 'blocks' && (
          <>
            <div className="px-1 py-1 text-[10px] text-base-content/30 bg-base-200/50 rounded">
              CMS blocks from the block editor. Click to add as a text frame with block content.
            </div>
            {['typography', 'content', 'layout', 'media', 'blog', 'interactive', 'data', 'commerce', 'forms', 'embed'].map(cat => {
              const items = blockGroups.get(cat);
              if (!items || items.length === 0) return null;
              return (
                <div key={cat}>
                  <div className="text-[9px] font-medium uppercase tracking-wider text-base-content/25 mb-1 px-1">{cat} ({items.length})</div>
                  <div className="space-y-0">
                    {items.map(item => (
                      <button key={item.type} onClick={() => handleAddBlock(item.type)}
                        className="w-full flex items-center gap-2 px-2 py-1 rounded text-left text-[11px] text-base-content/60 hover:bg-base-300/15 hover:text-base-content/80 transition-colors">
                        {item.label}
                      </button>
                    ))}
                  </div>
                </div>
              );
            })}

            {/* Catch uncategorized */}
            {Array.from(blockGroups.entries())
              .filter(([cat]) => !['typography', 'content', 'layout', 'media', 'blog', 'interactive', 'data', 'commerce', 'forms', 'embed'].includes(cat))
              .map(([cat, items]) => (
                <div key={cat}>
                  <div className="text-[9px] font-medium uppercase tracking-wider text-base-content/25 mb-1 px-1">{cat} ({items.length})</div>
                  {items.map(item => (
                    <button key={item.type} onClick={() => handleAddBlock(item.type)}
                      className="w-full flex items-center gap-2 px-2 py-1 rounded text-left text-[11px] text-base-content/60 hover:bg-base-300/15 transition-colors">
                      {item.label}
                    </button>
                  ))}
                </div>
              ))}
          </>
        )}
      </div>
    </div>
  );
}
