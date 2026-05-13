import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Plus, Trash2, GripVertical, ChevronRight, ChevronDown,
  Loader2, ExternalLink, FileText, FolderOpen, Link2, Globe, ArrowRight,
  ArrowLeft as IndentLeft, Settings2, Eye, EyeOff,
} from 'lucide-react';
import { menus, pages as pagesApi, categories as catsApi, posts as postsApi } from '@/lib/api';

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

function getItemType(item: MenuItemData): ItemType {
  if (item.page_id) return 'page';
  if (item.post_id) return 'post';
  if (item.category_id) return 'category';
  return 'custom';
}

function getItemTypeBadge(type: ItemType) {
  switch (type) {
    case 'page': return { label: 'Page', color: 'bg-blue-100 text-blue-700' };
    case 'post': return { label: 'Post', color: 'bg-purple-100 text-purple-700' };
    case 'category': return { label: 'Category', color: 'bg-green-100 text-green-700' };
    default: return { label: 'Link', color: 'bg-gray-100 text-gray-600' };
  }
}

function getResolvedUrl(item: MenuItemData): string {
  if (item.url) return item.url;
  if (item.page_id && item.page) {
    return item.page.slug === 'home' ? '/' : `/${item.page.slug}`;
  }
  if (item.post_id && item.post) return `/blog/${item.post.slug}`;
  if (item.category_id && item.category) return `/blog/category/${item.category.slug}`;
  return '#';
}

export default function MenuEditor() {
  const { siteId = '', menuId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [items, setItems] = useState<MenuItemData[]>([]);
  const [menuName, setMenuName] = useState('');
  const [isDirty, setIsDirty] = useState(false);
  const [addPanelTab, setAddPanelTab] = useState<'pages' | 'posts' | 'categories' | 'custom'>('pages');
  const [searchQuery, setSearchQuery] = useState('');

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
      setItems(menuData.items || []);
    }
  }, [menuData]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      await menus.update(siteId, menuId, { name: menuName });
      const cleaned = cleanItems(items);
      await menus.syncItems(siteId, menuId, cleaned);
    },
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['menu', siteId, menuId] });
    },
  });

  const cleanItems = (items: MenuItemData[]): unknown[] =>
    items.map((item, i) => ({
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

  // Deep update helper
  const updateAtPath = useCallback((path: number[], updater: (items: MenuItemData[]) => MenuItemData[]) => {
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    if (path.length === 0) {
      setItems(updater(newItems));
    } else {
      let target = newItems;
      for (let i = 0; i < path.length - 1; i++) {
        target = target[path[i]].children;
      }
      const parentArr = path.length > 1 ? target : newItems;
      const idx = path[path.length - 1];
      // For single-item operations
      const result = updater(parentArr);
      if (path.length === 1) {
        setItems(result);
      } else {
        // Replace the parent's children
        let p = newItems;
        for (let i = 0; i < path.length - 2; i++) {
          p = p[path[i]].children;
        }
        p[path[path.length - 2]].children = result;
        setItems(newItems);
      }
    }
    setIsDirty(true);
  }, [items]);

  const updateItem = (path: number[], field: string, value: unknown) => {
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    let target: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    (target[path[path.length - 1]] as Record<string, unknown>)[field] = value;
    setItems(newItems);
    setIsDirty(true);
  };

  const removeItem = (path: number[]) => {
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    let target: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    target.splice(path[path.length - 1], 1);
    setItems(newItems);
    setIsDirty(true);
  };

  // Move item up in its sibling list
  const moveUp = (path: number[]) => {
    const idx = path[path.length - 1];
    if (idx === 0) return;
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    let target: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    [target[idx - 1], target[idx]] = [target[idx], target[idx - 1]];
    setItems(newItems);
    setIsDirty(true);
  };

  // Move item down in its sibling list
  const moveDown = (path: number[], siblingCount: number) => {
    const idx = path[path.length - 1];
    if (idx >= siblingCount - 1) return;
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    let target: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    [target[idx], target[idx + 1]] = [target[idx + 1], target[idx]];
    setItems(newItems);
    setIsDirty(true);
  };

  // Indent: make this item a child of the previous sibling
  const indentItem = (path: number[]) => {
    const idx = path[path.length - 1];
    if (idx === 0) return; // can't indent first item
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    let target: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    const item = target.splice(idx, 1)[0];
    if (!target[idx - 1].children) target[idx - 1].children = [];
    target[idx - 1].children.push(item);
    setItems(newItems);
    setIsDirty(true);
  };

  // Outdent: move this item out of its parent to the level above
  const outdentItem = (path: number[]) => {
    if (path.length < 2) return; // can't outdent top-level
    const newItems = JSON.parse(JSON.stringify(items)) as MenuItemData[];
    // Navigate to parent's children array
    let parentArr: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 2; i++) {
      parentArr = parentArr[path[i]].children;
    }
    const parentIdx = path[path.length - 2];
    const childIdx = path[path.length - 1];
    const item = parentArr[parentIdx].children.splice(childIdx, 1)[0];
    // Insert after parent in parent's level
    let grandparentArr: MenuItemData[] = newItems;
    for (let i = 0; i < path.length - 2; i++) {
      grandparentArr = grandparentArr[path[i]].children;
    }
    // For top-level parents, grandparentArr is newItems
    if (path.length === 2) {
      newItems.splice(parentIdx + 1, 0, item);
    } else {
      let gp: MenuItemData[] = newItems;
      for (let i = 0; i < path.length - 3; i++) {
        gp = gp[path[i]].children;
      }
      gp[path[path.length - 3]].children.splice(parentIdx + 1, 0, item);
    }
    setItems(newItems);
    setIsDirty(true);
  };

  const addCustomLink = () => {
    setItems([...items, {
      label: 'New Link', url: '', target: '_self', sort_order: items.length, children: [],
    }]);
    setIsDirty(true);
  };

  const addPageItem = (page: { id: string; title: string; slug?: string }) => {
    setItems([...items, {
      label: page.title,
      page_id: page.id,
      page: { id: page.id, title: page.title, slug: page.slug || '' } as RelatedPage,
      target: '_self',
      sort_order: items.length,
      children: [],
    }]);
    setIsDirty(true);
  };

  const addPostItem = (post: { id: string; title: string; slug?: string }) => {
    setItems([...items, {
      label: post.title,
      post_id: post.id,
      post: { id: post.id, title: post.title, slug: post.slug || '' } as RelatedPost,
      target: '_self',
      sort_order: items.length,
      children: [],
    }]);
    setIsDirty(true);
  };

  const addCategoryItem = (cat: { id: string; name: string; slug?: string }) => {
    setItems([...items, {
      label: cat.name,
      category_id: cat.id,
      category: { id: cat.id, name: cat.name, slug: cat.slug || '' } as RelatedCategory,
      target: '_self',
      sort_order: items.length,
      children: [],
    }]);
    setIsDirty(true);
  };

  // Filter helper for search
  const filterBySearch = <T extends { title?: string; name?: string; slug?: string }>(list: T[]): T[] => {
    if (!searchQuery) return list;
    const q = searchQuery.toLowerCase();
    return list.filter(item =>
      (item.title || item.name || '').toLowerCase().includes(q) ||
      (item.slug || '').toLowerCase().includes(q)
    );
  };

  const renderItem = (item: MenuItemData, path: number[], depth: number = 0, siblingCount: number = 1) => {
    const type = getItemType(item);
    const badge = getItemTypeBadge(type);
    const resolvedUrl = getResolvedUrl(item);
    const hasChildren = item.children && item.children.length > 0;
    const isExpanded = item._expanded !== false; // default expanded
    const settingsOpen = item._settingsOpen === true;
    const idx = path[path.length - 1];

    return (
      <div key={path.join('-')} style={{ marginLeft: depth * 20 }}>
        <div className={`group flex flex-col bg-white border rounded-lg shadow-sm mb-1.5 ${settingsOpen ? 'border-blue-300 ring-1 ring-blue-100' : 'border-gray-200'}`}>
          {/* Main row */}
          <div className="flex items-center gap-1.5 px-2.5 py-2">
            <GripVertical className="h-3.5 w-3.5 text-gray-300 cursor-grab shrink-0" />

            {/* Expand/collapse for items with children */}
            {hasChildren ? (
              <button
                type="button"
                onClick={() => updateItem(path, '_expanded', !isExpanded)}
                className="p-0.5 text-gray-400 hover:text-gray-600"
              >
                {isExpanded ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
              </button>
            ) : (
              <span className="w-4.5" />
            )}

            {/* Type badge */}
            <span className={`shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded ${badge.color}`}>
              {badge.label}
            </span>

            {/* Label input */}
            <input
              value={item.label}
              onChange={(e) => updateItem(path, 'label', e.target.value)}
              className="flex-1 min-w-0 px-1.5 py-0.5 text-sm font-medium border-0 bg-transparent focus:outline-none focus:ring-0"
              placeholder="Menu label"
            />

            {/* Resolved URL preview */}
            <span className="hidden sm:block text-[11px] text-gray-400 truncate max-w-[200px]" title={resolvedUrl}>
              {resolvedUrl}
            </span>

            {/* Target indicator */}
            {item.target === '_blank' && (
              <ExternalLink className="h-3 w-3 text-gray-300 shrink-0" title="Opens in new tab" />
            )}

            {/* Action buttons */}
            <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
              {/* Move up/down */}
              <button onClick={() => moveUp(path)} disabled={idx === 0}
                className="p-0.5 text-gray-300 hover:text-gray-500 disabled:invisible" title="Move up">
                <ChevronRight className="h-3 w-3 -rotate-90" />
              </button>
              <button onClick={() => moveDown(path, siblingCount)} disabled={idx >= siblingCount - 1}
                className="p-0.5 text-gray-300 hover:text-gray-500 disabled:invisible" title="Move down">
                <ChevronRight className="h-3 w-3 rotate-90" />
              </button>

              {/* Indent/Outdent */}
              {idx > 0 && (
                <button onClick={() => indentItem(path)} className="p-0.5 text-gray-300 hover:text-blue-500" title="Indent (make sub-item)">
                  <ArrowRight className="h-3 w-3" />
                </button>
              )}
              {depth > 0 && (
                <button onClick={() => outdentItem(path)} className="p-0.5 text-gray-300 hover:text-blue-500" title="Outdent (move up a level)">
                  <IndentLeft className="h-3 w-3" />
                </button>
              )}

              {/* Settings toggle */}
              <button onClick={() => updateItem(path, '_settingsOpen', !settingsOpen)}
                className={`p-0.5 ${settingsOpen ? 'text-blue-500' : 'text-gray-300 hover:text-gray-500'}`} title="Settings">
                <Settings2 className="h-3 w-3" />
              </button>

              {/* Delete */}
              <button onClick={() => removeItem(path)} className="p-0.5 text-gray-300 hover:text-red-500" title="Remove">
                <Trash2 className="h-3 w-3" />
              </button>
            </div>
          </div>

          {/* Expanded settings panel */}
          {settingsOpen && (
            <div className="border-t border-gray-100 px-3 py-2.5 bg-gray-50/50 rounded-b-lg space-y-2">
              <div className="grid grid-cols-2 gap-2">
                {/* URL field — only for custom links */}
                {type === 'custom' && (
                  <div className="col-span-2">
                    <label className="text-[10px] font-medium text-gray-500">URL</label>
                    <input
                      value={item.url || ''}
                      onChange={(e) => updateItem(path, 'url', e.target.value || null)}
                      className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                      placeholder="https://example.com or /page"
                    />
                  </div>
                )}

                {/* Page/Post/Category info — read-only */}
                {type === 'page' && item.page && (
                  <div className="col-span-2 flex items-center gap-2 text-xs text-gray-500 bg-blue-50 px-2 py-1.5 rounded">
                    <FileText className="h-3.5 w-3.5 text-blue-400" />
                    <div>
                      <span className="font-medium text-blue-700">{item.page.title}</span>
                      <span className="ml-1.5 text-blue-400">/{item.page.slug}</span>
                    </div>
                  </div>
                )}
                {type === 'post' && item.post && (
                  <div className="col-span-2 flex items-center gap-2 text-xs text-gray-500 bg-purple-50 px-2 py-1.5 rounded">
                    <FileText className="h-3.5 w-3.5 text-purple-400" />
                    <div>
                      <span className="font-medium text-purple-700">{item.post.title}</span>
                      <span className="ml-1.5 text-purple-400">/blog/{item.post.slug}</span>
                    </div>
                  </div>
                )}
                {type === 'category' && item.category && (
                  <div className="col-span-2 flex items-center gap-2 text-xs text-gray-500 bg-green-50 px-2 py-1.5 rounded">
                    <FolderOpen className="h-3.5 w-3.5 text-green-400" />
                    <div>
                      <span className="font-medium text-green-700">{item.category.name}</span>
                      <span className="ml-1.5 text-green-400">/blog/category/{item.category.slug}</span>
                    </div>
                  </div>
                )}

                {/* Resolved URL — for non-custom items */}
                {type !== 'custom' && (
                  <div className="col-span-2">
                    <label className="text-[10px] font-medium text-gray-500">Resolved URL</label>
                    <div className="mt-0.5 px-2 py-1 text-xs bg-gray-100 rounded text-gray-600 font-mono">
                      {resolvedUrl}
                    </div>
                  </div>
                )}

                {/* Target */}
                <div>
                  <label className="text-[10px] font-medium text-gray-500">Open in</label>
                  <select
                    value={item.target}
                    onChange={(e) => updateItem(path, 'target', e.target.value)}
                    className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="_self">Same tab</option>
                    <option value="_blank">New tab</option>
                  </select>
                </div>

                {/* CSS Class */}
                <div>
                  <label className="text-[10px] font-medium text-gray-500">CSS Class</label>
                  <input
                    value={item.css_class || ''}
                    onChange={(e) => updateItem(path, 'css_class', e.target.value || null)}
                    className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="optional"
                  />
                </div>

                {/* Icon */}
                <div>
                  <label className="text-[10px] font-medium text-gray-500">Icon</label>
                  <input
                    value={item.icon || ''}
                    onChange={(e) => updateItem(path, 'icon', e.target.value || null)}
                    className="w-full mt-0.5 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="e.g. home, menu, star"
                  />
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Children */}
        {hasChildren && isExpanded && (
          <div>
            {item.children.map((child, ci) => renderItem(child, [...path, ci], depth + 1, item.children.length))}
          </div>
        )}
      </div>
    );
  };

  if (isLoading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  const pagesList = filterBySearch((sitePages as Array<{ id: string; title: string; slug: string; status: string }>) || []);
  const postsList = filterBySearch((sitePosts as Array<{ id: string; title: string; slug: string }>) || []);
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
            <input
              value={menuName}
              onChange={(e) => { setMenuName(e.target.value); setIsDirty(true); }}
              className="text-2xl font-bold text-gray-900 bg-transparent border-none outline-none focus:ring-0 p-0"
              placeholder="Menu name"
            />
            <div className="flex items-center gap-2 mt-0.5">
              <span className="text-xs text-gray-400">{items.length} item{items.length !== 1 ? 's' : ''}</span>
              {isDirty && <span className="text-xs text-orange-500 font-medium">Unsaved changes</span>}
            </div>
          </div>
        </div>
        <button
          onClick={() => saveMutation.mutate()}
          disabled={saveMutation.isPending || !isDirty}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
        >
          {saveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Save Menu
        </button>
      </div>

      <div className="flex gap-6">
        {/* Items list */}
        <div className="flex-1 min-w-0">
          {items.length === 0 ? (
            <div className="text-center py-16 bg-white rounded-xl border border-dashed border-gray-300">
              <Globe className="h-10 w-10 text-gray-300 mx-auto mb-3" />
              <p className="text-sm font-medium text-gray-500">No menu items yet</p>
              <p className="text-xs text-gray-400 mt-1">Add pages, posts, categories, or custom links from the panel on the right.</p>
            </div>
          ) : (
            <div>
              {items.map((item, i) => renderItem(item, [i], 0, items.length))}
            </div>
          )}
        </div>

        {/* Add items panel */}
        <div className="w-72 shrink-0">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-4">
            <div className="px-4 py-3 border-b border-gray-100">
              <h3 className="font-semibold text-gray-900 text-sm">Add Menu Items</h3>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-gray-100">
              {([
                { key: 'pages' as const, label: 'Pages', icon: FileText },
                { key: 'posts' as const, label: 'Posts', icon: FileText },
                { key: 'categories' as const, label: 'Cats', icon: FolderOpen },
                { key: 'custom' as const, label: 'Link', icon: Link2 },
              ]).map(({ key, label, icon: Icon }) => (
                <button
                  key={key}
                  onClick={() => { setAddPanelTab(key); setSearchQuery(''); }}
                  className={`flex-1 flex items-center justify-center gap-1 px-2 py-2 text-[11px] font-medium border-b-2 transition-colors ${
                    addPanelTab === key
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-400 hover:text-gray-600'
                  }`}
                >
                  <Icon className="h-3 w-3" />
                  {label}
                </button>
              ))}
            </div>

            {/* Search */}
            {addPanelTab !== 'custom' && (
              <div className="px-3 py-2 border-b border-gray-50">
                <input
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search..."
                  className="w-full px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
            )}

            {/* Content */}
            <div className="max-h-[400px] overflow-y-auto">
              {addPanelTab === 'pages' && (
                <div className="p-2 space-y-0.5">
                  {pagesList.length === 0 ? (
                    <p className="text-xs text-gray-400 text-center py-4">No pages found</p>
                  ) : pagesList.map((page: { id: string; title: string; slug: string; status: string }) => (
                    <button
                      key={page.id}
                      onClick={() => addPageItem(page)}
                      className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-blue-50 rounded-lg transition-colors group"
                    >
                      <FileText className="h-3.5 w-3.5 text-gray-300 group-hover:text-blue-400" />
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-medium text-gray-700 truncate">{page.title}</div>
                        <div className="text-[10px] text-gray-400 truncate">/{page.slug}</div>
                      </div>
                      <Plus className="h-3 w-3 text-gray-300 group-hover:text-blue-500" />
                    </button>
                  ))}
                </div>
              )}

              {addPanelTab === 'posts' && (
                <div className="p-2 space-y-0.5">
                  {postsList.length === 0 ? (
                    <p className="text-xs text-gray-400 text-center py-4">No posts found</p>
                  ) : postsList.map((post: { id: string; title: string; slug: string }) => (
                    <button
                      key={post.id}
                      onClick={() => addPostItem(post)}
                      className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-purple-50 rounded-lg transition-colors group"
                    >
                      <FileText className="h-3.5 w-3.5 text-gray-300 group-hover:text-purple-400" />
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-medium text-gray-700 truncate">{post.title}</div>
                        <div className="text-[10px] text-gray-400 truncate">/blog/{post.slug}</div>
                      </div>
                      <Plus className="h-3 w-3 text-gray-300 group-hover:text-purple-500" />
                    </button>
                  ))}
                </div>
              )}

              {addPanelTab === 'categories' && (
                <div className="p-2 space-y-0.5">
                  {catsList.length === 0 ? (
                    <p className="text-xs text-gray-400 text-center py-4">No categories found</p>
                  ) : catsList.map((cat: { id: string; name: string; slug: string }) => (
                    <button
                      key={cat.id}
                      onClick={() => addCategoryItem(cat)}
                      className="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-green-50 rounded-lg transition-colors group"
                    >
                      <FolderOpen className="h-3.5 w-3.5 text-gray-300 group-hover:text-green-400" />
                      <div className="flex-1 min-w-0">
                        <div className="text-xs font-medium text-gray-700 truncate">{cat.name}</div>
                        <div className="text-[10px] text-gray-400 truncate">/blog/category/{cat.slug}</div>
                      </div>
                      <Plus className="h-3 w-3 text-gray-300 group-hover:text-green-500" />
                    </button>
                  ))}
                </div>
              )}

              {addPanelTab === 'custom' && (
                <div className="p-4 space-y-3">
                  <p className="text-xs text-gray-500">Add a custom link with any URL — external site, anchor, mailto, etc.</p>
                  <button
                    onClick={addCustomLink}
                    className="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium text-blue-600 border-2 border-dashed border-blue-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors"
                  >
                    <Plus className="h-4 w-4" />
                    Add Custom Link
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
