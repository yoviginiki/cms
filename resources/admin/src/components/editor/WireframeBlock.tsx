/**
 * WireframeBlock — structural outline renderer for Wireframe editor mode.
 *
 * Renders blocks as labeled boxes showing hierarchy instead of live content.
 * Same data, different visual representation than SortableBlock (Visual mode).
 */

import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData } from '@/types/blocks';
import { ChevronRight, ChevronDown, Plus } from 'lucide-react';
import { BlockIcon } from './BlockIcon';
import { ModulePicker } from './ModulePicker';
import { LAYOUT_GRID, type RowLayout } from '@/components/blocks/row/definition';
import { useState, useRef } from 'react';

// Map block types to accent colors for wireframe boxes
const typeColors: Record<string, string> = {
  section: 'border-blue-300 bg-blue-50/50',
  row: 'border-green-300 bg-green-50/30',
  column: 'border-purple-300 bg-purple-50/30',
  columns: 'border-purple-300 bg-purple-50/30',
  container: 'border-green-300 bg-green-50/30',
  heading: 'border-gray-300 bg-white',
  paragraph: 'border-gray-300 bg-white',
  text: 'border-gray-300 bg-white',
  'rich-text': 'border-gray-300 bg-white',
  image: 'border-amber-300 bg-amber-50/30',
  button: 'border-indigo-300 bg-indigo-50/30',
  hero: 'border-rose-300 bg-rose-50/30',
};

// Level badge colors
const levelColors: Record<string, string> = {
  section: 'bg-blue-100 text-blue-700',
  row: 'bg-green-100 text-green-700',
  column: 'bg-purple-100 text-purple-700',
  module: 'bg-gray-100 text-gray-600',
};

function getBlockLabel(block: BlockData): string {
  const reg = blockRegistry.get(block.type);
  const label = reg?.definition.label || block.type;
  const data = block.data ?? {};

  // Show content preview if available
  const title = (data.title as string) || (data.text as string) || (data.heading as string) || '';
  if (title) return `${label}: "${title.slice(0, 30)}${title.length > 30 ? '...' : ''}"`;

  // Show layout info for rows
  if (block.type === 'row' && data.layout) {
    return `${label} (${data.layout})`;
  }

  return label;
}

// Quick-add child mapping
const quickAddChild: Record<string, { type: string; label: string; color: string }> = {
  section: { type: 'row', label: 'Row', color: 'text-green-600 hover:bg-green-50' },
  row: { type: 'column', label: 'Column', color: 'text-purple-600 hover:bg-purple-50' },
  column: { type: 'heading', label: 'Heading', color: 'text-blue-600 hover:bg-blue-50' },
};

interface WireframeBlockProps {
  block: BlockData;
  depth?: number;
}

export function WireframeBlock({ block, depth = 0 }: WireframeBlockProps) {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const addBlock = useEditorStore((s) => s.addBlock);
  const [expanded, setExpanded] = useState(true);
  const [pickerOpen, setPickerOpen] = useState(false);
  const addBtnRef = useRef<HTMLButtonElement>(null);

  const reg = blockRegistry.get(block.type);
  const isSelected = selectedBlockId === block.id;
  const children = block.children ?? [];
  const hasChildren = children.length > 0;
  const iconName = reg?.definition.icon || 'Box';
  const colorClass = typeColors[block.type] || 'border-gray-300 bg-gray-50/30';
  const level = block.level || 'module';
  const levelColor = levelColors[level] || levelColors.module;
  const childConfig = quickAddChild[level];

  return (
    <div style={{ marginLeft: depth * 16 }}>
      <div
        className={`flex items-center gap-2 px-3 py-2 rounded-md border cursor-pointer transition-all mb-1 ${colorClass} ${
          isSelected
            ? 'ring-2 ring-blue-500 ring-offset-1 border-blue-500'
            : 'hover:border-blue-200'
        }`}
        onClick={(e) => {
          e.stopPropagation();
          selectBlock(block.id);
        }}
      >
        {/* Expand/collapse for blocks with children */}
        {hasChildren ? (
          <button
            onClick={(e) => { e.stopPropagation(); setExpanded(!expanded); }}
            className="p-0.5 text-gray-400 hover:text-gray-600"
          >
            {expanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
          </button>
        ) : (
          <span className="w-[22px]" />
        )}

        {/* Type icon */}
        <BlockIcon icon={iconName} size={14} className="text-gray-400 shrink-0" />

        {/* Label */}
        <span className="text-xs font-medium text-gray-700 truncate flex-1">
          {getBlockLabel(block)}
        </span>

        {/* Level badge */}
        <span className={`text-[9px] uppercase tracking-wider shrink-0 rounded px-1.5 py-0.5 font-medium ${levelColor}`}>
          {level}
        </span>

        {/* Children count */}
        {hasChildren && (
          <span className="text-[9px] bg-gray-200 text-gray-500 rounded px-1 py-0.5">
            {children.length}
          </span>
        )}

        {/* Quick-add child button */}
        {childConfig && (
          <button
            ref={level === 'column' ? addBtnRef : undefined}
            onClick={(e) => {
              e.stopPropagation();
              if (level === 'column') setPickerOpen(true);
              else addBlock(childConfig.type, block.id);
            }}
            className={`p-0.5 rounded ${childConfig.color} transition-colors`}
            title={`Add ${childConfig.label}`}
          >
            <Plus size={12} />
          </button>
        )}
      </div>

      {/* Children — rows render columns horizontally */}
      {hasChildren && expanded && (
        level === 'row' ? (
          <div
            className="ml-2 pl-1 gap-2"
            style={{
              display: 'grid',
              gridTemplateColumns: LAYOUT_GRID[(block.data ?? {}).layout as RowLayout] || `repeat(${children.length}, 1fr)`,
            }}
          >
            {children.map((child) => (
              <WireframeBlock key={child.id} block={child} depth={0} />
            ))}
          </div>
        ) : (
          <div className="ml-2 border-l border-gray-200 pl-1">
            {children.map((child) => (
              <WireframeBlock key={child.id} block={child} depth={depth + 1} />
            ))}
          </div>
        )
      )}

      {/* Module picker for columns */}
      {pickerOpen && (
        <ModulePicker
          parentId={block.id}
          onClose={() => setPickerOpen(false)}
          anchorEl={addBtnRef.current}
        />
      )}
    </div>
  );
}
