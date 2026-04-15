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
      window.location.href = '/admin/login';
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
};

export const pages = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/pages`, { params }),
  get: (siteId: string, pageId: string) => api.get(`/sites/${siteId}/pages/${pageId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/pages`, data),
  update: (siteId: string, pageId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/pages/${pageId}`, data),
  delete: (siteId: string, pageId: string) => api.delete(`/sites/${siteId}/pages/${pageId}`),
  reorder: (siteId: string, items: unknown[]) => api.post(`/sites/${siteId}/pages/reorder`, { items }),
};

export const blocks = {
  get: (siteId: string, type: 'pages' | 'posts', id: string) =>
    api.get(`/sites/${siteId}/${type}/${id}/blocks`),
  sync: (siteId: string, type: 'pages' | 'posts', id: string, data: unknown[]) =>
    api.put(`/sites/${siteId}/${type}/${id}/blocks`, { blocks: data }),
};

export const posts = {
  list: (siteId: string, params?: Record<string, unknown>) => api.get(`/sites/${siteId}/posts`, { params }),
  get: (siteId: string, postId: string) => api.get(`/sites/${siteId}/posts/${postId}`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/posts`, data),
  update: (siteId: string, postId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/posts/${postId}`, data),
  delete: (siteId: string, postId: string) => api.delete(`/sites/${siteId}/posts/${postId}`),
};

export const categories = {
  list: (siteId: string) => api.get(`/sites/${siteId}/categories`),
  create: (siteId: string, data: Record<string, unknown>) => api.post(`/sites/${siteId}/categories`, data),
  update: (siteId: string, catId: string, data: Record<string, unknown>) => api.put(`/sites/${siteId}/categories/${catId}`, data),
  delete: (siteId: string, catId: string) => api.delete(`/sites/${siteId}/categories/${catId}`),
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
  publish: (siteId: string) => api.post(`/sites/${siteId}/publish`),
  status: (siteId: string, deploymentId: string) => api.get(`/sites/${siteId}/publish/${deploymentId}`),
};

export default api;
