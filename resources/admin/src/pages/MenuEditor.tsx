import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save, Plus, Trash2, GripVertical, ChevronRight, Loader2 } from 'lucide-react';
import { menus, pages as pagesApi, categories as catsApi } from '@/lib/api';

interface MenuItemData {
  id?: string;
  label: string;
  url?: string | null;
  page_id?: string | null;
  post_id?: string | null;
  category_id?: string | null;
  target: string;
  css_class?: string | null;
  sort_order: number;
  children: MenuItemData[];
  _expanded?: boolean;
}

export default function MenuEditor() {
  const { siteId = '', menuId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [items, setItems] = useState<MenuItemData[]>([]);
  const [menuName, setMenuName] = useState('');
  const [isDirty, setIsDirty] = useState(false);

  const { data: menuData, isLoading } = useQuery({
    queryKey: ['menu', siteId, menuId],
    queryFn: () => menus.get(siteId, menuId).then(r => r.data.data),
  });

  const { data: sitePages } = useQuery({
    queryKey: ['pages-list', siteId],
    queryFn: () => pagesApi.list(siteId).then(r => r.data.data),
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
      category_id: item.category_id || null,
      target: item.target || '_self',
      css_class: item.css_class || null,
      sort_order: i,
      children: cleanItems(item.children || []),
    }));

  const addItem = (type: 'custom' | 'page' | 'category') => {
    let newItem: MenuItemData = { label: 'New Link', url: '#', target: '_self', sort_order: items.length, children: [] };

    if (type === 'page' && sitePages?.length) {
      const page = sitePages[0];
      newItem = { label: page.title, page_id: page.id, target: '_self', sort_order: items.length, children: [] };
    } else if (type === 'category' && siteCats?.length) {
      const cat = siteCats[0];
      newItem = { label: cat.name, category_id: cat.id, target: '_self', sort_order: items.length, children: [] };
    }

    setItems([...items, newItem]);
    setIsDirty(true);
  };

  const updateItem = (path: number[], field: string, value: string | null) => {
    const newItems = JSON.parse(JSON.stringify(items));
    let target = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    target[path[path.length - 1]][field] = value;
    setItems(newItems);
    setIsDirty(true);
  };

  const removeItem = (path: number[]) => {
    const newItems = JSON.parse(JSON.stringify(items));
    let target = newItems;
    for (let i = 0; i < path.length - 1; i++) {
      target = target[path[i]].children;
    }
    target.splice(path[path.length - 1], 1);
    setItems(newItems);
    setIsDirty(true);
  };

  const renderItem = (item: MenuItemData, path: number[], depth: number = 0) => (
    <div key={path.join('-')} style={{ marginLeft: depth * 24 }} className="mb-2">
      <div className="flex items-center gap-2 bg-white border border-gray-200 rounded-lg p-3 shadow-sm">
        <GripVertical className="h-4 w-4 text-gray-300 cursor-grab" />
        <input
          value={item.label}
          onChange={(e) => updateItem(path, 'label', e.target.value)}
          className="flex-1 px-2 py-1 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
          placeholder="Label"
        />
        <input
          value={item.url || ''}
          onChange={(e) => updateItem(path, 'url', e.target.value || null)}
          className="w-48 px-2 py-1 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
          placeholder={item.page_id ? '(linked to page)' : 'URL'}
          disabled={!!item.page_id || !!item.category_id}
        />
        <select
          value={item.target}
          onChange={(e) => updateItem(path, 'target', e.target.value)}
          className="px-2 py-1 text-xs border border-gray-200 rounded"
        >
          <option value="_self">Same tab</option>
          <option value="_blank">New tab</option>
        </select>
        <button onClick={() => removeItem(path)} className="p-1 text-gray-400 hover:text-red-500"><Trash2 className="h-3.5 w-3.5" /></button>
      </div>
      {item.children?.map((child, ci) => renderItem(child, [...path, ci], depth + 1))}
    </div>
  );

  if (isLoading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/menus`)} className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
            <ArrowLeft className="h-5 w-5" />
          </button>
          <div>
            <input
              value={menuName}
              onChange={(e) => { setMenuName(e.target.value); setIsDirty(true); }}
              className="text-2xl font-bold text-gray-900 bg-transparent border-none outline-none focus:ring-0 p-0"
            />
            {isDirty && <span className="text-xs text-orange-500 font-medium ml-2">Unsaved</span>}
          </div>
        </div>
        <button
          onClick={() => saveMutation.mutate()}
          disabled={saveMutation.isPending || !isDirty}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {saveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Save
        </button>
      </div>

      <div className="flex gap-6">
        {/* Items list */}
        <div className="flex-1">
          {items.length === 0 && (
            <div className="text-center py-12 text-gray-400">
              <p className="text-sm">No menu items yet. Add items from the panel on the right.</p>
            </div>
          )}
          {items.map((item, i) => renderItem(item, [i]))}
        </div>

        {/* Add items panel */}
        <div className="w-64 shrink-0">
          <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
            <h3 className="font-medium text-gray-900 text-sm">Add Items</h3>
            <button onClick={() => addItem('custom')} className="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50">
              <Plus className="h-4 w-4" /> Custom Link
            </button>

            {sitePages && sitePages.length > 0 && (
              <div>
                <p className="text-xs font-medium text-gray-500 mb-1">Pages</p>
                <div className="max-h-32 overflow-y-auto space-y-1">
                  {(sitePages as Array<{id: string; title: string}>).map((page) => (
                    <button
                      key={page.id}
                      onClick={() => { setItems([...items, { label: page.title, page_id: page.id, target: '_self', sort_order: items.length, children: [] }]); setIsDirty(true); }}
                      className="w-full flex items-center gap-1 px-2 py-1 text-xs text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded"
                    >
                      <ChevronRight className="h-3 w-3" />{page.title}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {siteCats && siteCats.length > 0 && (
              <div>
                <p className="text-xs font-medium text-gray-500 mb-1">Categories</p>
                <div className="max-h-32 overflow-y-auto space-y-1">
                  {(siteCats as Array<{id: string; name: string}>).map((cat) => (
                    <button
                      key={cat.id}
                      onClick={() => { setItems([...items, { label: cat.name, category_id: cat.id, target: '_self', sort_order: items.length, children: [] }]); setIsDirty(true); }}
                      className="w-full flex items-center gap-1 px-2 py-1 text-xs text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded"
                    >
                      <ChevronRight className="h-3 w-3" />{cat.name}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
