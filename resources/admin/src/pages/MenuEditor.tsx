import { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  DndContext, closestCenter, PointerSensor, useSensor, useSensors,
  type DragEndEvent, type DragStartEvent, type DragMoveEvent, DragOverlay,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  ArrowLeft, Save, Plus, Trash2, GripVertical, ChevronRight, ChevronDown,
  Loader2, ExternalLink, FileText, FolderOpen, Link2, Globe, Settings2,
} from 'lucide-react';
import { menus, pages as pagesApi, categories as catsApi, posts as postsApi } from '@/lib/api';

// ── Types ──

interface RelatedPage { id: string; title: string; slug: string }
interface RelatedPost { id: string; title: string; slug: string }
interface RelatedCategory { id: string; name: string; slug: string }

interface MenuItemData {
  id?: string;
  label: string;
  url?: string | null;
  page_id?: string | null;
  post_id?: string | null;
  category_id?: string | null;
  page?: RelatedPage | null;
  post?: RelatedPost | null;
  category?: RelatedCategory | null;
  target: string;
  css_class?: string | null;
  icon?: string | null;
  sort_order: number;
  children: MenuItemData[];
  _expanded?: boolean;
  _settingsOpen?: boolean;
}

type ItemType = 'custom' | 'page' | 'post' | 'category';

// ── Constants ──

const INDENT_PX = 32; // pixels per depth level
const DEPTH_DRAG_PX = 40; // horizontal pixels to change one depth level during drag

// ── Helpers ──

let _nextTempId = 1;
function tempId() { return `_t${_nextTempId++}_${Date.now()}`; }

function ensureIds(items: MenuItemData[]): MenuItemData[] {
  return items.map(item => ({
    ...item,
    id: item.id || tempId(),
    children: ensureIds(item.children || []),
  }));
}

function getItemType(item: MenuItemData): ItemType {
  if (item.page_id) return 'page';
  if (item.post_id) return 'post';
  if (item.category_id) return 'category';
  return 'custom';
}

function getTypeBadge(type: ItemType) {
  switch (type) {
    case 'page': return { label: 'Page', cls: 'bg-blue-100 text-blue-700' };
    case 'post': return { label: 'Post', cls: 'bg-purple-100 text-purple-700' };
    case 'category': return { label: 'Cat', cls: 'bg-green-100 text-green-700' };
    default: return { label: 'Link', cls: 'bg-gray-100 text-gray-600' };
  }
}

function getResolvedUrl(item: MenuItemData): string {
  if (item.url) return item.url;
  if (item.page_id && item.page) return item.page.slug === 'home' ? '/' : `/${item.page.slug}`;
  if (item.post_id && item.post) return `/blog/${item.post.slug}`;
  if (item.category_id && item.category) return `/blog/category/${item.category.slug}`;
  return '#';
}

// Flatten tree for DnD — each node gets a stable position in the flat list
interface FlatItem {
  item: MenuItemData;
  depth: number;
  index: number; // index in flat list
}

function flattenTree(items: MenuItemData[], depth = 0, visibleOnly = false): FlatItem[] {
  const result: FlatItem[] = [];
  for (const item of items) {
    result.push({ item, depth, index: 0 });
    const shouldDescend = visibleOnly ? (item._expanded !== false) : true;
    if (item.children?.length && shouldDescend) {
      result.push(...flattenTree(item.children, depth + 1, visibleOnly));
    }
  }
  result.forEach((fi, i) => fi.index = i);
  return result;
}

// Remove item by id from tree, return [newTree, removedItem, removedDepth]
function removeById(items: MenuItemData[], id: string, depth = 0): [MenuItemData[], MenuItemData | null, number] {
  for (let i = 0; i < items.length; i++) {
    if (items[i].id === id) {
      const removed = items[i];
      return [[...items.slice(0, i), ...items.slice(i + 1)], removed, depth];
    }
    if (items[i].children?.length) {
      const [newChildren, found, d] = removeById(items[i].children, id, depth + 1);
      if (found) {
        const newItems = [...items];
        newItems[i] = { ...newItems[i], children: newChildren };
        return [newItems, found, d];
      }
    }
  }
  return [items, null, -1];
}

// Rebuild tree from a flat list of { item, targetDepth }
function rebuildTree(flatList: { item: MenuItemData; depth: number }[]): MenuItemData[] {
  const root: MenuItemData[] = [];
  const stack: { items: MenuItemData[]; depth: number }[] = [{ items: root, depth: -1 }];

  for (const { item, depth } of flatList) {
    const node: MenuItemData = { ...item, children: [] };

    // Pop stack to find the correct parent
    while (stack.length > 1 && stack[stack.length - 1].depth >= depth) {
      stack.pop();
    }

    stack[stack.length - 1].items.push(node);
    stack.push({ items: node.children, depth });
  }

  return root;
}

// Collect all items with labels for parent selector (flat list with depth)
function collectParentOptions(items: MenuItemData[], depth = 0): { id: string; label: string; depth: number }[] {
  const result: { id: string; label: string; depth: number }[] = [];
  for (const item of items) {
    result.push({ id: item.id || '', label: item.label, depth });
    if (item.children?.length) {
      result.push(...collectParentOptions(item.children, depth + 1));
    }
  }
  return result;
}

// Collect all descendant IDs of an item (for filtering parent dropdown)
function collectDescendantIds(items: MenuItemData[], targetId: string): Set<string> {
  const ids = new Set<string>();
  const collect = (list: MenuItemData[]) => {
    for (const item of list) {
      ids.add(item.id || '');
      if (item.children?.length) collect(item.children);
    }
  };
  // Find the target item and collect its descendants
  const find = (list: MenuItemData[]): boolean => {
    for (const item of list) {
      if (item.id === targetId) {
        if (item.children?.length) collect(item.children);
        return true;
      }
      if (item.children?.length && find(item.children)) return true;
    }
    return false;
  };
  find(items);
  ids.add(targetId); // include self
  return ids;
}

// Add item as child of parentId (or root if null). Falls back to root if parentId not found.
function addToParent(items: MenuItemData[], newItem: MenuItemData, parentId: string | null): MenuItemData[] {
  if (!parentId) return [...items, newItem];
  let found = false;
  const result = items.map(item => {
    if (item.id === parentId) {
      found = true;
      return { ...item, children: [...(item.children || []), newItem], _expanded: true };
    }
    if (item.children?.length) {
      const newChildren = addToParent(item.children, newItem, parentId);
      if (newChildren !== item.children) { found = true; return { ...item, children: newChildren }; }
    }
    return item;
  });
  // Fallback: if parent not found, add to root
  return found ? result : [...items, newItem];
}

// ── Sortable Item Component ──

interface SortableMenuItemProps {
  flatItem: FlatItem;
  isOver: boolean;
  projectedDepth: number | null;
  onUpdate: (id: string, field: string, value: unknown) => void;
  onRemove: (id: string) => void;
  onChangeParent: (id: string, newParentId: string | null) => void;
  parentOptions: { id: string; label: string; depth: number }[];
  excludedParentIds: Set<string>;
  currentParentId: string | null;
  isDragOverlay?: boolean;
}

function SortableMenuItem({ flatItem, isOver, projectedDepth, onUpdate, onRemove, onChangeParent, parentOptions, excludedParentIds, currentParentId, isDragOverlay }: SortableMenuItemProps) {
  const { item, depth } = flatItem;
  const itemId = item.id || '';

  const {
    attributes, listeners, setNodeRef, transform, transition, isDragging,
  } = useSortable({ id: itemId, disabled: isDragOverlay });

  const style = isDragOverlay ? {} : {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.15 : 1,
  };

  const type = getItemType(item);
  const badge = getTypeBadge(type);
  const resolvedUrl = getResolvedUrl(item);
  const hasChildren = item.children && item.children.length > 0;
  const isExpanded = item._expanded !== false;
  const settingsOpen = item._settingsOpen === true;

  const displayDepth = isDragOverlay ? 0 : depth;

  return (
    <div ref={setNodeRef} style={style}>
      {/* Drop indicator line showing projected depth */}
      {isOver && projectedDepth !== null && !isDragOverlay && (
        <div
          className="h-0.5 bg-blue-500 rounded-full mb-0.5 transition-all"
          style={{ marginLeft: projectedDepth * INDENT_PX }}
        />
      )}
      <div
        className={`group flex flex-col bg-white border rounded-lg shadow-sm mb-1 ${
          settingsOpen ? 'border-blue-300 ring-1 ring-blue-100' :
          isDragging ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200'
        } ${displayDepth > 0 ? 'border-l-[3px] border-l-blue-300' : ''}`}
        style={{ marginLeft: displayDepth * INDENT_PX }}
      >
        {/* Main row */}
        <div className="flex items-center gap-1.5 px-2 py-1.5">
          {/* Drag handle */}
          <button
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing p-0.5 text-gray-300 hover:text-gray-500 touch-none"
            tabIndex={-1}
            title="Drag to reorder. Drag left/right to change nesting."
          >
            <GripVertical className="h-4 w-4" />
          </button>

          {/* Expand/collapse */}
          {hasChildren ? (
            <button type="button" onClick={() => onUpdate(itemId, '_expanded', !isExpanded)}
              className="p-0.5 text-gray-400 hover:text-gray-600">
              {isExpanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
            </button>
          ) : <span className="w-[18px]" />}

          {/* Type badge */}
          <span className={`shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded ${badge.cls}`}>
            {badge.label}
          </span>

          {/* Label */}
          <input
            value={item.label}
            onChange={(e) => onUpdate(itemId, 'label', e.target.value)}
            className="flex-1 min-w-0 px-1.5 py-0.5 text-sm font-medium border-0 bg-transparent focus:outline-none focus:ring-0"
            placeholder="Menu label"
          />

          {/* URL preview */}
          <span className="hidden md:block text-[11px] text-gray-400 truncate max-w-[160px] font-mono" title={resolvedUrl}>
            {resolvedUrl}
          </span>

          {item.target === '_blank' && (
            <ExternalLink className="h-3 w-3 text-gray-300 shrink-0" title="Opens in new tab" />
          )}

          {/* Actions */}
          <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
            <button onClick={() => onUpdate(itemId, '_settingsOpen', !settingsOpen)}
              className={`p-0.5 ${settingsOpen ? 'text-blue-500' : 'text-gray-300 hover:text-gray-500'}`} title="Settings">
              <Settings2 className="h-3.5 w-3.5" />
            </button>
            <button onClick={() => onRemove(itemId)} className="p-0.5 text-gray-300 hover:text-red-500" title="Remove">
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        {/* Settings panel */}
        {settingsOpen && (
          <div className="border-t border-gray-100 px-3 py-2.5 bg-gray-50/50 rounded-b-lg">
            <div className="grid grid-cols-2 gap-2">
              {/* Parent selector — move item under another parent */}
              <div className="col-span-2">
                <label className="text-[10px] font-medium text-gray-500">Parent Item</label>
                <select
                  value={currentParentId || ''}
                  onChange={(e) => onChangeParent(itemId, e.target.value || null)}
                  className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                  <option value="">Top level (root)</option>
                  {parentOptions
                    .filter(opt => !excludedParentIds.has(opt.id)) // exclude self and all descendants
                    .map((opt) => (
                      <option key={opt.id} value={opt.id}>
                        {'—'.repeat(opt.depth)} {opt.label}
                      </option>
                    ))}
                </select>
              </div>

              {type === 'custom' && (
                <div className="col-span-2">
                  <label className="text-[10px] font-medium text-gray-500">URL</label>
                  <input value={item.url || ''} onChange={(e) => onUpdate(itemId, 'url', e.target.value || null)}
                    className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="https://example.com or /page" />
                </div>
              )}
              {type === 'page' && item.page && (
                <div className="col-span-2 flex items-center gap-2 text-xs bg-blue-50 px-2 py-1.5 rounded">
                  <FileText className="h-3.5 w-3.5 text-blue-400" />
                  <span className="font-medium text-blue-700">{item.page.title}</span>
                  <span className="text-blue-400">/{item.page.slug}</span>
                </div>
              )}
              {type === 'post' && item.post && (
                <div className="col-span-2 flex items-center gap-2 text-xs bg-purple-50 px-2 py-1.5 rounded">
                  <FileText className="h-3.5 w-3.5 text-purple-400" />
                  <span className="font-medium text-purple-700">{item.post.title}</span>
                  <span className="text-purple-400">/blog/{item.post.slug}</span>
                </div>
              )}
              {type === 'category' && item.category && (
                <div className="col-span-2 flex items-center gap-2 text-xs bg-green-50 px-2 py-1.5 rounded">
                  <FolderOpen className="h-3.5 w-3.5 text-green-400" />
                  <span className="font-medium text-green-700">{item.category.name}</span>
                  <span className="text-green-400">/blog/category/{item.category.slug}</span>
                </div>
              )}
              {type !== 'custom' && (
                <div className="col-span-2">
                  <label className="text-[10px] font-medium text-gray-500">Resolved URL</label>
                  <div className="mt-0.5 px-2 py-1 text-xs bg-gray-100 rounded text-gray-600 font-mono">{resolvedUrl}</div>
                </div>
              )}
              <div>
                <label className="text-[10px] font-medium text-gray-500">Open in</label>
                <select value={item.target} onChange={(e) => onUpdate(itemId, 'target', e.target.value)}
                  className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded">
                  <option value="_self">Same tab</option>
                  <option value="_blank">New tab</option>
                </select>
              </div>
              <div>
                <label className="text-[10px] font-medium text-gray-500">CSS Class</label>
                <input value={item.css_class || ''} onChange={(e) => onUpdate(itemId, 'css_class', e.target.value || null)}
                  className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded" placeholder="optional" />
              </div>
              <div>
                <label className="text-[10px] font-medium text-gray-500">Icon</label>
                <input value={item.icon || ''} onChange={(e) => onUpdate(itemId, 'icon', e.target.value || null)}
                  className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded" placeholder="e.g. home, star" />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Main Editor ──

export default function MenuEditor() {
  const { siteId = '', menuId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [items, setItems] = useState<MenuItemData[]>([]);
  const [menuName, setMenuName] = useState('');
  const [isDirty, setIsDirty] = useState(false);
  const [addPanelTab, setAddPanelTab] = useState<'pages' | 'posts' | 'categories' | 'custom'>('pages');
  const [searchQuery, setSearchQuery] = useState('');
  const [addParentId, setAddParentId] = useState<string | null>(null);

  // DnD state
  const [activeId, setActiveId] = useState<string | null>(null);
  const [overId, setOverId] = useState<string | null>(null);
  const [dragDeltaX, setDragDeltaX] = useState(0);
  const dragDeltaRef = useRef(0); // ref for race-free read in handleDragEnd

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  const { data: menuData, isLoading } = useQuery({
    queryKey: ['menu', siteId, menuId],
    queryFn: () => menus.get(siteId, menuId).then(r => r.data.data),
  });
  const { data: sitePages } = useQuery({
    queryKey: ['pages-list', siteId],
    queryFn: () => pagesApi.list(siteId).then(r => r.data.data),
  });
  const { data: sitePosts } = useQuery({
    queryKey: ['posts-list', siteId],
    queryFn: () => postsApi.list(siteId).then(r => r.data.data),
  });
  const { data: siteCats } = useQuery({
    queryKey: ['cats-list', siteId],
    queryFn: () => catsApi.list(siteId).then(r => r.data.data),
  });

  useEffect(() => {
    if (menuData) {
      setMenuName(menuData.menu?.name || '');
      setItems(ensureIds(menuData.items || []));
    }
  }, [menuData]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      await menus.update(siteId, menuId, { name: menuName });
      await menus.syncItems(siteId, menuId, cleanItems(items));
    },
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['menu', siteId, menuId] });
    },
  });

  const cleanItems = (list: MenuItemData[]): unknown[] =>
    list.map((item, i) => ({
      label: item.label,
      url: item.url || null,
      page_id: item.page_id || null,
      post_id: item.post_id || null,
      category_id: item.category_id || null,
      target: item.target || '_self',
      css_class: item.css_class || null,
      icon: item.icon || null,
      sort_order: i,
      children: cleanItems(item.children || []),
    }));

  // Visible flat list for rendering/DnD context (respects collapsed state)
  const flatItems = useMemo(() => flattenTree(items, 0, true), [items]);
  const flatIds = useMemo(() => flatItems.map(fi => fi.item.id || ''), [flatItems]);

  // Calculate projected depth during drag — shows where the item will land
  // Clamped to ±1 level for same-item drags (actual behavior is one level at a time)
  const projectedDepth = useMemo(() => {
    if (!activeId) return null;
    const activeFlat = flatItems.find(fi => fi.item.id === activeId);
    if (!activeFlat) return null;

    const depthChange = Math.round(dragDeltaX / DEPTH_DRAG_PX);

    if (overId && overId !== activeId) {
      const overFlat = flatItems.find(fi => fi.item.id === overId);
      if (overFlat) {
        const maxDepth = overFlat.depth + 1;
        return Math.max(0, Math.min(maxDepth, activeFlat.depth + depthChange));
      }
    }

    // Same-item: clamp to ±1 level (matches actual indent/outdent behavior)
    const clampedChange = Math.max(-1, Math.min(1, depthChange));
    return Math.max(0, activeFlat.depth + clampedChange);
  }, [activeId, overId, dragDeltaX, flatItems]);

  // DnD handlers
  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id as string);
    setDragDeltaX(0);
    dragDeltaRef.current = 0;
  };

  const handleDragMove = (event: DragMoveEvent) => {
    dragDeltaRef.current = event.delta.x;
    setDragDeltaX(event.delta.x);
    setOverId(event.over ? (event.over.id as string) : null);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    const finalDeltaX = dragDeltaRef.current;
    setActiveId(null);
    setOverId(null);
    setDragDeltaX(0);

    if (!over) return;

    const activeFlat = flatItems.find(fi => fi.item.id === active.id);
    if (!activeFlat) return;

    const significantHorizontal = Math.abs(finalDeltaX) >= DEPTH_DRAG_PX;
    const sameItem = active.id === over.id;

    // Case 1: Dragged horizontally on the same item — indent/outdent in place
    if (sameItem && significantHorizontal) {
      const depthChange = Math.round(finalDeltaX / DEPTH_DRAG_PX);
      const targetDepth = activeFlat.depth + depthChange;

      if (targetDepth < activeFlat.depth && activeFlat.depth > 0) {
        // Outdent: find this item's parent and move it out
        const currentParent = parentMap[active.id as string];
        if (currentParent) {
          const [treeWithout, removed] = removeById(items, active.id as string);
          if (removed) {
            // Find the parent in the flat list to insert after it
            const parentFlat = flattenTree(treeWithout).find(fi => fi.item.id === currentParent);
            if (parentFlat) {
              const fullFlat = flattenTree(treeWithout);
              // Find all items that belong under the parent (to insert after the last one)
              let insertAfterIdx = fullFlat.findIndex(fi => fi.item.id === currentParent);
              // Skip past all descendants of the parent
              for (let i = insertAfterIdx + 1; i < fullFlat.length; i++) {
                if (fullFlat[i].depth > parentFlat.depth) insertAfterIdx = i;
                else break;
              }
              const newFlatList = fullFlat.map(fi => ({ item: fi.item, depth: fi.depth }));
              const newDepth = parentFlat.depth; // same level as parent
              newFlatList.splice(insertAfterIdx + 1, 0, { item: { ...removed, children: [] }, depth: newDepth });
              // Re-insert children of removed item
              if (removed.children?.length) {
                const childFlat = flattenTree(removed.children, newDepth + 1);
                for (let i = 0; i < childFlat.length; i++) {
                  newFlatList.splice(insertAfterIdx + 2 + i, 0, { item: { ...childFlat[i].item, children: [] }, depth: childFlat[i].depth });
                }
              }
              setItems(rebuildTree(newFlatList));
              setIsDirty(true);
            }
          }
        }
        return;
      }

      if (targetDepth > activeFlat.depth) {
        // Indent: find the previous sibling and make this item its child
        const activeIdx = flatItems.indexOf(activeFlat);
        // Find the previous item at the same depth
        let prevSiblingId: string | null = null;
        for (let i = activeIdx - 1; i >= 0; i--) {
          if (flatItems[i].depth === activeFlat.depth) { prevSiblingId = flatItems[i].item.id || null; break; }
          if (flatItems[i].depth < activeFlat.depth) break; // went above our level
        }
        if (prevSiblingId) {
          const [treeWithout, removed] = removeById(items, active.id as string);
          if (removed) {
            const newTree = addToParent(treeWithout, removed, prevSiblingId);
            setItems(newTree);
            setIsDirty(true);
          }
        }
        return;
      }

      return; // no meaningful depth change
    }

    // Case 2: Dropped on a different item — reorder (and optionally change depth)
    if (sameItem) return; // no vertical movement and no significant horizontal

    const overFlat = flatItems.find(fi => fi.item.id === over.id);
    if (!overFlat) return;

    // Guard: don't drop on own descendant (would silently delete subtree)
    const activeDescendants = collectDescendantIds(items, active.id as string);
    if (activeDescendants.has(over.id as string)) return;

    // Remove active from tree
    const [treeAfterRemove, removed] = removeById(items, active.id as string);
    if (!removed) return;

    // Flatten the tree without the active item
    const remainingFlat = flattenTree(treeAfterRemove);

    // Find where "over" is in the remaining flat list
    const overRemainingIdx = remainingFlat.findIndex(fi => fi.item.id === over.id);
    if (overRemainingIdx === -1) return; // safety — don't commit partial removal

    // Calculate target depth: start from active item's original depth + horizontal change
    const depthChange = significantHorizontal ? Math.round(finalDeltaX / DEPTH_DRAG_PX) : 0;
    const desiredDepth = activeFlat.depth + depthChange;
    // Clamp: can't go below 0, can't go deeper than the over item's depth + 1
    const maxDepth = remainingFlat[overRemainingIdx].depth + 1;
    const targetDepth = Math.max(0, Math.min(maxDepth, desiredDepth));

    // Determine insert position
    const activeOrigIdx = flatItems.indexOf(activeFlat);
    const overOrigIdx = flatItems.indexOf(overFlat);
    const insertIdx = activeOrigIdx < overOrigIdx ? overRemainingIdx + 1 : overRemainingIdx;

    // Build new flat list
    const newFlatList: { item: MenuItemData; depth: number }[] = remainingFlat.map(fi => ({
      item: fi.item,
      depth: fi.depth,
    }));
    newFlatList.splice(insertIdx, 0, { item: { ...removed, children: [] }, depth: targetDepth });

    // Re-insert children of removed item
    if (removed.children?.length) {
      const removedChildrenFlat = flattenTree(removed.children, targetDepth + 1);
      for (let i = 0; i < removedChildrenFlat.length; i++) {
        newFlatList.splice(insertIdx + 1 + i, 0, {
          item: { ...removedChildrenFlat[i].item, children: [] },
          depth: removedChildrenFlat[i].depth,
        });
      }
    }

    const newTree = rebuildTree(newFlatList);
    setItems(newTree);
    setIsDirty(true);
  };

  // Item update by id (searches tree)
  const updateItemById = (id: string, field: string, value: unknown) => {
    const update = (list: MenuItemData[]): MenuItemData[] =>
      list.map(item => {
        if (item.id === id) return { ...item, [field]: value };
        if (item.children?.length) return { ...item, children: update(item.children) };
        return item;
      });
    setItems(update(items));
    if (field !== '_expanded' && field !== '_settingsOpen') setIsDirty(true);
  };

  const removeItemById = (id: string) => {
    const [newItems] = removeById(items, id);
    setItems(newItems);
    setIsDirty(true);
    // Clear addParentId if the removed item (or any of its descendants) was the selected parent
    if (addParentId) {
      const stillExists = collectParentOptions(newItems).some(opt => opt.id === addParentId);
      if (!stillExists) setAddParentId(null);
    }
  };

  // Change parent of an existing item
  const changeParentById = (itemId: string, newParentId: string | null) => {
    // Remove item from current position
    const [treeWithout, removed] = removeById(items, itemId);
    if (!removed) return;
    // Insert into new parent (with children preserved)
    const newTree = addToParent(treeWithout, removed, newParentId);
    setItems(newTree);
    setIsDirty(true);
  };

  // Build a map of itemId -> parentId for quick lookup
  const parentMap = useMemo(() => {
    const map: Record<string, string | null> = {};
    const walk = (list: MenuItemData[], parentId: string | null) => {
      for (const item of list) {
        if (item.id) map[item.id] = parentId;
        if (item.children?.length) walk(item.children, item.id || null);
      }
    };
    walk(items, null);
    return map;
  }, [items]);

  // Add helpers
  const addItem = (newItem: MenuItemData) => {
    const withId = { ...newItem, id: tempId() };
    setItems(addToParent(items, withId, addParentId));
    setIsDirty(true);
  };

  const filterBySearch = <T extends { title?: string; name?: string; slug?: string }>(list: T[]): T[] => {
    if (!searchQuery) return list;
    const q = searchQuery.toLowerCase();
    return list.filter(item => (item.title || item.name || '').toLowerCase().includes(q) || (item.slug || '').toLowerCase().includes(q));
  };

  // Parent options for "Add to" selector
  const parentOptions = useMemo(() => collectParentOptions(items), [items]);

  // Active drag overlay
  const activeFlatItem = activeId ? flatItems.find(fi => fi.item.id === activeId) : null;

  if (isLoading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  const pagesList = filterBySearch(((sitePages as Array<{ id: string; title: string; slug: string; status: string }>) || []).filter(p => p.status === 'published'));
  const postsList = filterBySearch(((sitePosts as Array<{ id: string; title: string; slug: string; status: string }>) || []).filter(p => p.status === 'published'));
  const catsList = filterBySearch((siteCats as Array<{ id: string; name: string; slug: string }>) || []);

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/menus`)} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div>
            <input value={menuName} onChange={(e) => { setMenuName(e.target.value); setIsDirty(true); }}
              className="text-2xl font-bold text-gray-900 bg-transparent border-none outline-none focus:ring-0 p-0" placeholder="Menu name" />
            <div className="flex items-center gap-2 mt-0.5">
              <span className="text-xs text-gray-400">{items.length} item{items.length !== 1 ? 's' : ''}</span>
              {isDirty && <span className="text-xs text-orange-500 font-medium">Unsaved changes</span>}
            </div>
          </div>
        </div>
        <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !isDirty}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors">
          {saveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Save Menu
        </button>
      </div>

      <div className="flex gap-6">
        {/* Items list with DnD */}
        <div className="flex-1 min-w-0">
          {items.length === 0 ? (
            <div className="text-center py-16 bg-white rounded-xl border border-dashed border-gray-300">
              <Globe className="h-10 w-10 text-gray-300 mx-auto mb-3" />
              <p className="text-sm font-medium text-gray-500">No menu items yet</p>
              <p className="text-xs text-gray-400 mt-1 max-w-xs mx-auto">
                Add pages, posts, categories, or custom links from the panel on the right.
                Drag items left/right to nest them as sub-menus.
              </p>
            </div>
          ) : (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragStart={handleDragStart}
              onDragMove={handleDragMove}
              onDragEnd={handleDragEnd}
            >
              <SortableContext items={flatIds} strategy={verticalListSortingStrategy}>
                {flatItems.map((fi) => (
                  <SortableMenuItem
                    key={fi.item.id}
                    flatItem={fi}
                    isOver={overId === fi.item.id || (activeId === fi.item.id && activeId === overId)}
                    projectedDepth={(overId === fi.item.id || (activeId === fi.item.id && !overId)) ? projectedDepth : null}
                    onUpdate={updateItemById}
                    onRemove={removeItemById}
                    onChangeParent={changeParentById}
                    parentOptions={parentOptions}
                    excludedParentIds={collectDescendantIds(items, fi.item.id || '')}
                    currentParentId={parentMap[fi.item.id || ''] ?? null}
                  />
                ))}
              </SortableContext>
              <DragOverlay dropAnimation={null}>
                {activeFlatItem && (
                  <div className="opacity-90 pointer-events-none" style={{ width: 500 }}>
                    <SortableMenuItem
                      flatItem={{ ...activeFlatItem, depth: 0 }}
                      isOver={false}
                      projectedDepth={null}
                      onUpdate={() => {}}
                      onRemove={() => {}}
                      onChangeParent={() => {}}
                      parentOptions={[]}
                      excludedParentIds={new Set()}
                      currentParentId={null}
                      isDragOverlay
                    />
                  </div>
                )}
              </DragOverlay>
            </DndContext>
          )}

          {/* Drag instructions */}
          {items.length > 0 && (
            <p className="text-[10px] text-gray-400 mt-2 text-center">
              Drag items up/down to reorder. Drag left/right to change nesting depth.
            </p>
          )}
        </div>

        {/* Add items panel */}
        <div className="w-72 shrink-0">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-4">
            <div className="px-4 py-3 border-b border-gray-100">
              <h3 className="font-semibold text-gray-900 text-sm">Add Menu Items</h3>
            </div>

            {/* Parent selector */}
            {parentOptions.length > 0 && (
              <div className="px-3 py-2 border-b border-gray-100 bg-gray-50/50">
                <label className="text-[10px] font-medium text-gray-500">Add to:</label>
                <select value={addParentId || ''} onChange={(e) => setAddParentId(e.target.value || null)}
                  className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded bg-white">
                  <option value="">Top level (root)</option>
                  {parentOptions.map((opt) => (
                    <option key={opt.id} value={opt.id}>
                      {'—'.repeat(opt.depth)} {opt.label}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Tabs */}
            <div className="flex border-b border-gray-100">
              {([
                { key: 'pages' as const, label: 'Pages', icon: FileText },
                { key: 'posts' as const, label: 'Posts', icon: FileText },
                { key: 'categories' as const, label: 'Cats', icon: FolderOpen },
                { key: 'custom' as const, label: 'Link', icon: Link2 },
              ]).map(({ key, label, icon: Icon }) => (
                <button key={key} onClick={() => { setAddPanelTab(key); setSearchQuery(''); }}
                  className={`flex-1 flex items-center justify-center gap-1 px-2 py-2 text-[11px] font-medium border-b-2 transition-colors ${
                    addPanelTab === key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'
                  }`}>
                  <Icon className="h-3 w-3" />{label}
                </button>
              ))}
            </div>

            {/* Search */}
            {addPanelTab !== 'custom' && (
              <div className="px-3 py-2 border-b border-gray-50">
                <input value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search..." className="w-full px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500" />
              </div>
            )}

            {/* Items */}
            <div className="max-h-[400px] overflow-y-auto">
              {addPanelTab === 'pages' && (
                <div className="p-2 space-y-0.5">
                  {pagesList.length === 0 ? <p className="text-xs text-gray-400 text-center py-4">No pages found</p> :
                    pagesList.map((page: any) => (
                      <button key={page.id} onClick={() => addItem({ label: page.title, page_id: page.id, page: { id: page.id, title: page.title, slug: page.slug || '' }, target: '_self', sort_order: 0, children: [] })}
                        className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-blue-50 rounded-lg transition-colors group">
                        <FileText className="h-3.5 w-3.5 text-gray-300 group-hover:text-blue-400" />
                        <div className="flex-1 min-w-0"><div className="text-xs font-medium text-gray-700 truncate">{page.title}</div><div className="text-[10px] text-gray-400 truncate">/{page.slug}</div></div>
                        <Plus className="h-3 w-3 text-gray-300 group-hover:text-blue-500" />
                      </button>
                    ))}
                </div>
              )}
              {addPanelTab === 'posts' && (
                <div className="p-2 space-y-0.5">
                  {postsList.length === 0 ? <p className="text-xs text-gray-400 text-center py-4">No posts found</p> :
                    postsList.map((post: any) => (
                      <button key={post.id} onClick={() => addItem({ label: post.title, post_id: post.id, post: { id: post.id, title: post.title, slug: post.slug || '' }, target: '_self', sort_order: 0, children: [] })}
                        className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-purple-50 rounded-lg transition-colors group">
                        <FileText className="h-3.5 w-3.5 text-gray-300 group-hover:text-purple-400" />
                        <div className="flex-1 min-w-0"><div className="text-xs font-medium text-gray-700 truncate">{post.title}</div><div className="text-[10px] text-gray-400 truncate">/blog/{post.slug}</div></div>
                        <Plus className="h-3 w-3 text-gray-300 group-hover:text-purple-500" />
                      </button>
                    ))}
                </div>
              )}
              {addPanelTab === 'categories' && (
                <div className="p-2 space-y-0.5">
                  {catsList.length === 0 ? <p className="text-xs text-gray-400 text-center py-4">No categories found</p> :
                    catsList.map((cat: any) => (
                      <button key={cat.id} onClick={() => addItem({ label: cat.name, category_id: cat.id, category: { id: cat.id, name: cat.name, slug: cat.slug || '' }, target: '_self', sort_order: 0, children: [] })}
                        className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-green-50 rounded-lg transition-colors group">
                        <FolderOpen className="h-3.5 w-3.5 text-gray-300 group-hover:text-green-400" />
                        <div className="flex-1 min-w-0"><div className="text-xs font-medium text-gray-700 truncate">{cat.name}</div><div className="text-[10px] text-gray-400 truncate">/blog/category/{cat.slug}</div></div>
                        <Plus className="h-3 w-3 text-gray-300 group-hover:text-green-500" />
                      </button>
                    ))}
                </div>
              )}
              {addPanelTab === 'custom' && (
                <div className="p-4 space-y-3">
                  <p className="text-xs text-gray-500">Add a custom link with any URL.</p>
                  <button onClick={() => addItem({ label: 'New Link', url: '', target: '_self', sort_order: 0, children: [] })}
                    className="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium text-blue-600 border-2 border-dashed border-blue-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <Plus className="h-4 w-4" /> Add Custom Link
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
