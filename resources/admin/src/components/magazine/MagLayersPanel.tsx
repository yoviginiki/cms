import { useState } from 'react';
import type { MagElement } from '@/types/magazine';

interface MagLayersPanelProps {
  elements: MagElement[];
  selectedIds: string[];
  onSelect: (id: string) => void;
  onToggleVisibility: (id: string) => void;
  onToggleLock: (id: string) => void;
  onReorderZ: (id: string, direction: 'up' | 'down') => void;
}

const TYPE_LABELS: Record<string, string> = {
  text_frame: 'Text',
  headline_frame: 'Headline',
  pullquote_frame: 'Pullquote',
  caption_frame: 'Caption',
  footnote_frame: 'Footnote',
  marginalia_frame: 'Marginalia',
  image_frame: 'Image',
  circular_image: 'Circle Image',
  polygon_image: 'Polygon Image',
  fullbleed_image: 'Fullbleed',
  gallery_frame: 'Gallery',
  background_image: 'Background',
  rectangle: 'Rectangle',
  ellipse: 'Ellipse',
  line: 'Line',
  polygon: 'Polygon',
  freeform_path: 'Path',
  decorative_rule: 'Rule',
  gradient_overlay: 'Gradient',
  video_frame: 'Video',
  audio_player: 'Audio',
  embed_frame: 'Embed',
  svg_icon: 'Icon',
  button: 'Button',
  hotspot: 'Hotspot',
  tooltip_trigger: 'Tooltip',
  accordion_frame: 'Accordion',
  slidein_panel: 'Slide-in',
  table_frame: 'Table',
  chart_frame: 'Chart',
  infographic_number: 'Infographic',
  progress_indicator: 'Progress',
  page_number: 'Page #',
  running_header: 'Header',
  column_guides: 'Columns',
  group: 'Group',
  component_instance: 'Component',
  clipping_group: 'Clip Group',
};

interface LayerRowProps {
  element: MagElement;
  displayName: string;
  isSelected: boolean;
  depth: number;
  onSelect: (id: string) => void;
  onToggleVisibility: (id: string) => void;
  onToggleLock: (id: string) => void;
  onReorderZ: (id: string, direction: 'up' | 'down') => void;
}

function LayerRow({
  element,
  displayName,
  isSelected,
  depth,
  onSelect,
  onToggleVisibility,
  onToggleLock,
  onReorderZ,
}: LayerRowProps) {
  const [collapsed, setCollapsed] = useState(false);
  const hasChildren = element.children && element.children.length > 0;
  const isGroup = element.type === 'group' || element.type === 'clipping_group';
  const typeLabel = TYPE_LABELS[element.type] || element.type;

  return (
    <>
      <div
        className={`flex items-center gap-1 px-2 py-1 cursor-pointer text-sm transition-colors
          ${isSelected ? 'bg-primary/5' : 'hover:bg-base-content/5'}`}
        style={{ paddingLeft: `${8 + depth * 16}px` }}
        onClick={() => onSelect(element.id)}
      >
        {/* Collapse toggle for groups */}
        {isGroup && hasChildren ? (
          <button
            className="btn btn-ghost btn-xs btn-square"
            onClick={(e) => {
              e.stopPropagation();
              setCollapsed(!collapsed);
            }}
          >
            <svg
              className={`w-3 h-3 transition-transform ${collapsed ? '' : 'rotate-90'}`}
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2}
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        ) : (
          <span className="w-5" />
        )}

        {/* Thread chain icon */}
        {element.threadId && (
          <svg className="w-3.5 h-3.5 text-base-content/40 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" />
          </svg>
        )}

        {/* Type label */}
        <span className="text-base-content/40 text-xs flex-shrink-0">{typeLabel}</span>

        {/* Element name */}
        <span className="truncate flex-1 text-base-content/80">{displayName}</span>

        {/* Reorder buttons */}
        <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            className="btn btn-ghost btn-xs btn-square"
            onClick={(e) => { e.stopPropagation(); onReorderZ(element.id, 'up'); }}
            title="Move up"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 15l7-7 7 7" />
            </svg>
          </button>
          <button
            className="btn btn-ghost btn-xs btn-square"
            onClick={(e) => { e.stopPropagation(); onReorderZ(element.id, 'down'); }}
            title="Move down"
          >
            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>

        {/* Visibility toggle */}
        <button
          className="btn btn-ghost btn-xs btn-square"
          onClick={(e) => { e.stopPropagation(); onToggleVisibility(element.id); }}
          title={element.visible ? 'Hide' : 'Show'}
        >
          {element.visible ? (
            <svg className="w-3.5 h-3.5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          ) : (
            <svg className="w-3.5 h-3.5 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
          )}
        </button>

        {/* Lock toggle */}
        <button
          className="btn btn-ghost btn-xs btn-square"
          onClick={(e) => { e.stopPropagation(); onToggleLock(element.id); }}
          title={element.locked ? 'Unlock' : 'Lock'}
        >
          {element.locked ? (
            <svg className="w-3.5 h-3.5 text-warning/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          ) : (
            <svg className="w-3.5 h-3.5 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
            </svg>
          )}
        </button>
      </div>

      {/* Group children */}
      {isGroup && hasChildren && !collapsed && (
        <div>
          {element.children.map((child, ci) => (
            <LayerRow
              key={child.id}
              element={child}
              displayName={child.name || `${TYPE_LABELS[child.type] || child.type} ${ci + 1}`}
              isSelected={false}
              depth={depth + 1}
              onSelect={onSelect}
              onToggleVisibility={onToggleVisibility}
              onToggleLock={onToggleLock}
              onReorderZ={onReorderZ}
            />
          ))}
        </div>
      )}
    </>
  );
}

export default function MagLayersPanel({
  elements,
  selectedIds,
  onSelect,
  onToggleVisibility,
  onToggleLock,
  onReorderZ,
}: MagLayersPanelProps) {
  // Sort by z-index descending (highest first)
  const sorted = [...elements].sort((a, b) => b.zIndex - a.zIndex);

  // Build per-type counters for auto-naming
  const typeCounters = new Map<string, number>();

  function getAutoName(el: MagElement): string {
    if (el.name) return el.name;
    const count = (typeCounters.get(el.type) || 0) + 1;
    typeCounters.set(el.type, count);
    const label = TYPE_LABELS[el.type] || el.type;
    return `${label} ${count}`;
  }

  return (
    <div className="flex flex-col h-full bg-base-200/50">
      <div className="px-3 py-2 border-b border-base-content/10">
        <h3 className="text-xs font-semibold uppercase tracking-wider text-base-content/50">
          Layers
        </h3>
      </div>

      <div className="flex-1 overflow-y-auto py-1">
        {sorted.length === 0 && (
          <p className="text-xs text-base-content/30 text-center py-6">No elements on this page</p>
        )}
        {sorted.map((el) => (
          <div key={el.id} className="group">
            <LayerRow
              element={el}
              displayName={getAutoName(el)}
              isSelected={selectedIds.includes(el.id)}
              depth={0}
              onSelect={onSelect}
              onToggleVisibility={onToggleVisibility}
              onToggleLock={onToggleLock}
              onReorderZ={onReorderZ}
            />
          </div>
        ))}
      </div>
    </div>
  );
}
