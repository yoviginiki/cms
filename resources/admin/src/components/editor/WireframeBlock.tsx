/**
 * WireframeBlock — structural outline renderer for Wireframe editor mode.
 *
 * Renders blocks as labeled boxes showing hierarchy instead of live content.
 * Same data, different visual representation than SortableBlock (Visual mode).
 *
 * This is a PROTOTYPE for architecture validation (Slice 0.6).
 */

import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData } from '@/types/blocks';
import { ChevronRight, ChevronDown, Type, Image, Layout, Columns, Box, MousePointer } from 'lucide-react';
import { useState } from 'react';

// Map block types to icons for wireframe display
const typeIcons: Record<string, typeof Type> = {
  heading: Type,
  paragraph: Type,
  text: Type,
  'rich-text': Type,
  image: Image,
  section: Layout,
  columns: Columns,
  container: Box,
  button: MousePointer,
};

// Map block types to accent colors for wireframe boxes
const typeColors: Record<string, string> = {
  section: 'border-blue-300 bg-blue-50/50',
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

function getBlockLabel(block: BlockData): string {
  const reg = blockRegistry.get(block.type);
  const label = reg?.definition.label || block.type;

  // Show content preview if available
  const title = (block.data.title as string) || (block.data.text as string) || (block.data.heading as string) || '';
  if (title) return `${label}: "${title.slice(0, 30)}${title.length > 30 ? '...' : ''}"`;

  return label;
}

interface WireframeBlockProps {
  block: BlockData;
  depth?: number;
}

export function WireframeBlock({ block, depth = 0 }: WireframeBlockProps) {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const [expanded, setExpanded] = useState(true);

  const isSelected = selectedBlockId === block.id;
  const hasChildren = block.children && block.children.length > 0;
  const Icon = typeIcons[block.type] || Box;
  const colorClass = typeColors[block.type] || 'border-gray-300 bg-gray-50/30';

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
        <Icon size={14} className="text-gray-400 shrink-0" />

        {/* Label */}
        <span className="text-xs font-medium text-gray-700 truncate flex-1">
          {getBlockLabel(block)}
        </span>

        {/* Type badge */}
        <span className="text-[9px] text-gray-400 uppercase tracking-wider shrink-0">
          {block.type}
        </span>

        {/* Children count */}
        {hasChildren && (
          <span className="text-[9px] bg-gray-200 text-gray-500 rounded px-1 py-0.5">
            {block.children.length}
          </span>
        )}
      </div>

      {/* Children */}
      {hasChildren && expanded && (
        <div className="ml-2 border-l border-gray-200 pl-1">
          {block.children.map((child) => (
            <WireframeBlock key={child.id} block={child} depth={depth + 1} />
          ))}
        </div>
      )}
    </div>
  );
}
