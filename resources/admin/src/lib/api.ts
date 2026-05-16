import axios from 'axios';

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = document.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
  )?.content;
  if (token) {
    config.headers['X-CSRF-TOKEN'] = token;
  }

  // Read XSRF token from cookie for Sanctum
  const xsrf = document.cookie
    .split('; ')
    .find((c) => c.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];
  if (xsrf) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Don't redirect if already on login page or if this is the login request
      const isLoginPage = window.location.pathname.includes('/login');
      const isLoginRequest = error.config?.url?.includes('/auth/login');
      if (!isLoginPage && !isLoginRequest) {
        window.location.href = '/admin/login';
      }
    }
    return Promise.reject(error);
  },
);

// API methods
export const sites = {
  list: () => api.get('/sites'),
  get: (id: string) => api.get(`/sites/${id}`),
  create: (data: Record<string, unknown>) => api.post('/sites', data),
  update: (id: string, data: Record<string, unknown>) => api.put(`/sites/${id}`, data),
  delete: (id: string) => api.delete(`/sites/${id}`),
  clone: (id: string, name: string) => api.post(`/sites/${id}/clone`, { name }),
  exportSite: (id: string) => api.post(`/sites/${id}/export`),
};

export const pages = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/pages`, { params }),
  get: (siteId: string, pageId: string) => api.get(`/sites/${siteId}/pages/${pageId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/pages`, data),
  update: (siteId: string, pageId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/pages/${pageId}`, data),
  delete: (siteId: string, pageId: string) => api.delete(`/sites/${siteId}/pages/${pageId}`),
  reorder: (siteId: string, items: unknown[]) => api.post(`/sites/${siteId}/pages/reorder`, { items }),
  diff: (siteId: string, pageId: string) => api.get(`/sites/${siteId}/pages/${pageId}/diff`),
};

export const blocks = {
  get: (siteId: string, type: 'pages' | 'posts', id: string) =>
    api.get(`/sites/${siteId}/${type}/${id}/blocks`),
  sync: (siteId: string, type: 'pages' | 'posts', id: string, data: unknown[], rawHtml?: string) =>
    api.put(`/sites/${siteId}/${type}/${id}/blocks`, { blocks: data, raw_html: rawHtml ?? '' }),
  render: (siteId: string, blockType: string, blockData: Record<string, unknown>) =>
    api.post(`/sites/${siteId}/blocks/render`, { type: blockType, data: blockData }),
};

export const posts = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/posts`, { params }),
  get: (siteId: string, postId: string) => api.get(`/sites/${siteId}/posts/${postId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/posts`, data),
  update: (siteId: string, postId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/posts/${postId}`, data),
  delete: (siteId: string, postId: string) => api.delete(`/sites/${siteId}/posts/${postId}`),
  diff: (siteId: string, postId: string) => api.get(`/sites/${siteId}/posts/${postId}/diff`),
};

export const categories = {
  list: (siteId: string) => api.get(`/sites/${siteId}/categories`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/categories`, data),
  update: (siteId: string, catId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/categories/${catId}`, data),
  delete: (siteId: string, catId: string) => api.delete(`/sites/${siteId}/categories/${catId}`),
};

export const tags = {
  list: (siteId: string) => api.get(`/sites/${siteId}/tags`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/tags`, data),
  update: (siteId: string, tagId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/tags/${tagId}`, data),
  delete: (siteId: string, tagId: string) => api.delete(`/sites/${siteId}/tags/${tagId}`),
  merge: (siteId: string, tagId: string, targetTagId: string) => api.post(`/sites/${siteId}/tags/${tagId}/merge`, { target_tag_id: targetTagId }),
};

export const menus = {
  list: (siteId: string) => api.get(`/sites/${siteId}/menus`),
  get: (siteId: string, menuId: string) => api.get(`/sites/${siteId}/menus/${menuId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/menus`, data),
  update: (siteId: string, menuId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/menus/${menuId}`, data),
  delete: (siteId: string, menuId: string) => api.delete(`/sites/${siteId}/menus/${menuId}`),
  syncItems: (siteId: string, menuId: string, items: unknown[]) => api.put(`/sites/${siteId}/menus/${menuId}/items`, { items }),
};

export const grids = {
  list: (siteId: string) => api.get(`/sites/${siteId}/grids`),
  get: (siteId: string, gridId: string) => api.get(`/sites/${siteId}/grids/${gridId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/grids`, data),
  update: (siteId: string, gridId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/grids/${gridId}`, data),
  delete: (siteId: string, gridId: string) => api.delete(`/sites/${siteId}/grids/${gridId}`),
  syncPositions: (siteId: string, gridId: string, positions: unknown[]) => api.put(`/sites/${siteId}/grids/${gridId}/positions`, { positions }),
  seedPresets: (siteId: string) => api.post(`/sites/${siteId}/grids/seed-presets`),
  assignments: (siteId: string) => api.get(`/sites/${siteId}/grid-assignments`),
  createAssignment: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/grid-assignments`, data),
  updateAssignment: (siteId: string, id: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/grid-assignments/${id}`, data),
  deleteAssignment: (siteId: string, id: string) => api.delete(`/sites/${siteId}/grid-assignments/${id}`),
};

export const versions = {
  listForPage: (siteId: string, pageId: string) => api.get(`/sites/${siteId}/pages/${pageId}/versions`),
  listForPost: (siteId: string, postId: string) => api.get(`/sites/${siteId}/posts/${postId}/versions`),
  restorePage: (siteId: string, pageId: string, versionId: string) => api.post(`/sites/${siteId}/pages/${pageId}/versions/${versionId}/restore`),
  restorePost: (siteId: string, postId: string, versionId: string) => api.post(`/sites/${siteId}/posts/${postId}/versions/${versionId}/restore`),
};

export const magazines = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/magazines`, { params }),
  get: (siteId: string, id: string) => api.get(`/sites/${siteId}/magazines/${id}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/magazines`, data),
  update: (siteId: string, id: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/magazines/${id}`, data),
  delete: (siteId: string, id: string) => api.delete(`/sites/${siteId}/magazines/${id}`),
  savePages: (siteId: string, id: string, pages: unknown[]) => api.put(`/sites/${siteId}/magazines/${id}/pages`, { pages }),
};

export const assets = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/assets`, { params }),
  upload: (siteId: string, file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    return api.post(`/sites/${siteId}/assets`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  delete: (siteId: string, assetId: string) => api.delete(`/sites/${siteId}/assets/${assetId}`),
};

export const publishing = {
  publish: (siteId: string, type: 'full' | 'partial' = 'partial') => api.post(`/sites/${siteId}/publish`, { type }),
  clear: (siteId: string) => api.post(`/sites/${siteId}/publish/clear`),
  status: (siteId: string, deploymentId: string) => api.get(`/sites/${siteId}/deployments/${deploymentId}`),
  history: (siteId: string) => api.get(`/sites/${siteId}/deployments`),
};

export const layouts = {
  list: (siteId: string) => api.get(`/sites/${siteId}/layouts`),
};

export const themeEngine = {
  list: (siteId: string) => api.get(`/sites/${siteId}/theme-engine/themes`),
  get: (siteId: string, themeId: string) => api.get(`/sites/${siteId}/theme-engine/themes/${themeId}`),
  update: (siteId: string, themeId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/theme-engine/themes/${themeId}`, data),
  fork: (siteId: string, themeId: string, name?: string) => api.post(`/sites/${siteId}/theme-engine/themes/${themeId}/fork`, { name }),
  exportTheme: (siteId: string, themeId: string) => api.get(`/sites/${siteId}/theme-engine/themes/${themeId}/export`),
  resolve: (siteId: string, mode?: string) => api.get(`/sites/${siteId}/theme-engine/resolve`, { params: { mode } }),
  assign: (siteId: string, themeId: string, mode?: string) => api.post(`/sites/${siteId}/theme-engine/assign`, { theme_id: themeId, mode }),
  saveOverrides: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/theme-engine/overrides`, data),
  importTheme: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/theme-engine/import`, data),
  versions: (siteId: string) => api.get(`/sites/${siteId}/theme-engine/versions`),
  coverage: (siteId: string, themeId: string, mode?: string) => api.get(`/sites/${siteId}/theme-engine/themes/${themeId}/coverage`, { params: { mode } }),
  studioFrames: (siteId: string) => api.get(`/sites/${siteId}/theme-engine/studio/frames`),
};

export const preview = {
  page: (siteId: string, pageId: string) => `/api/v1/sites/${siteId}/pages/${pageId}/preview`,
  post: (siteId: string, postId: string) => `/api/v1/sites/${siteId}/posts/${postId}/preview`,
  createToken: (siteId: string, contentType: string, contentId: string) =>
    api.post(`/sites/${siteId}/${contentType}/${contentId}/preview-token`),
};

export const wpImport = {
  upload: (siteId: string, file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    return api.post(`/sites/${siteId}/import/upload`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
  preview: (siteId: string, importId: string) => api.get(`/sites/${siteId}/import/${importId}/preview`),
  execute: (siteId: string, importId: string, options: Record<string, boolean>) =>
    api.post(`/sites/${siteId}/import/${importId}/execute`, options),
  status: (siteId: string, importId: string) => api.get(`/sites/${siteId}/import/${importId}/status`),
};

export const ai = {
  generate: (prompt: string, context?: unknown[]) => api.post('/ai/generate', { prompt, context }),
  rewrite: (content: string, instruction: string) => api.post('/ai/rewrite', { content, instruction }),
  translate: (content: string, language: string) => api.post('/ai/translate', { content, language }),
  seoSuggest: (siteId: string, pageId: string) => api.post(`/sites/${siteId}/pages/${pageId}/ai/seo`),
  altText: (siteId: string, assetId: string) => api.post(`/sites/${siteId}/assets/${assetId}/ai/alt-text`),
};

export const editor = {
  heartbeat: (data: { page_id?: string; post_id?: string }) => api.post('/editor/heartbeat', data),
  presence: (contentType: string, contentId: string) => api.get(`/editor/presence/${contentType}/${contentId}`),
};

export const templates = {
  list: (params?: Record<string, unknown>) => api.get('/templates', { params }),
  preview: (templateId: string) => api.get(`/templates/${templateId}/preview`),
  install: (templateId: string, siteId: string) => api.post(`/templates/${templateId}/install/${siteId}`),
};

export const system = {
  checkUpdate: () => api.get('/system/updates'),
  applyUpdate: (data: { version: string; download_url: string; checksum: string }) =>
    api.post('/system/updates/apply', data),
};

export const magEditor = {
  get: (siteId: string, pageId: string) => api.get(`/sites/${siteId}/pages/${pageId}/magazine`),
  sync: (siteId: string, pageId: string, data: { pages: unknown[]; elements: unknown[] }) => api.put(`/sites/${siteId}/pages/${pageId}/magazine`, data),
  addPage: (siteId: string, pageId: string, afterPage: number) => api.post(`/sites/${siteId}/pages/${pageId}/magazine/pages`, { after_page: afterPage }),
  deletePage: (siteId: string, pageId: string, pageNumber: number) => api.delete(`/sites/${siteId}/pages/${pageId}/magazine/pages/${pageNumber}`),
};

export const magStyles = {
  list: (siteId: string) => api.get(`/sites/${siteId}/magazine-styles`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/magazine-styles`, data),
  update: (siteId: string, styleId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/magazine-styles/${styleId}`, data),
  delete: (siteId: string, styleId: string) => api.delete(`/sites/${siteId}/magazine-styles/${styleId}`),
};

export const issueComposer = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/magazine-issues`, { params }),
  get: (siteId: string, issueId: string) => api.get(`/sites/${siteId}/magazine-issues/${issueId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/magazine-issues`, data),
  update: (siteId: string, issueId: string, data: Record<string, unknown>) => api.patch(`/sites/${siteId}/magazine-issues/${issueId}`, data),
  delete: (siteId: string, issueId: string) => api.delete(`/sites/${siteId}/magazine-issues/${issueId}`),
  addItem: (siteId: string, issueId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/magazine-issues/${issueId}/content-items`, data),
  updateItem: (siteId: string, issueId: string, itemId: string, data: Record<string, unknown>) => api.patch(`/sites/${siteId}/magazine-issues/${issueId}/content-items/${itemId}`, data),
  removeItem: (siteId: string, issueId: string, itemId: string) => api.delete(`/sites/${siteId}/magazine-issues/${issueId}/content-items/${itemId}`),
  runLayout: (siteId: string, issueId: string) => api.post(`/sites/${siteId}/magazine-issues/${issueId}/layout/run`),
  handoff: (siteId: string, issueId: string) => api.post(`/sites/${siteId}/magazine-issues/${issueId}/handoff`),
};

export default api;
