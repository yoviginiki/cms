import { api } from '@/lib/api';
import type { StudioSession, SessionSummary } from './types';

const BASE = '/issue-studio/sessions';

export const studioApi = {
  list: (siteId: string) =>
    api.get(BASE, { params: { site_id: siteId } }).then(r => r.data.data as SessionSummary[]),

  create: (siteId: string, title?: string) =>
    api.post(BASE, { site_id: siteId, title }).then(r => r.data.data as StudioSession),

  get: (id: string) =>
    api.get(`${BASE}/${id}`).then(r => r.data.data as StudioSession),

  abandon: (id: string) =>
    api.delete(`${BASE}/${id}`),

  sendMessage: (id: string, content: string) =>
    api.post(`${BASE}/${id}/messages`, { content }).then(r => r.data.data as StudioSession),

  addTextMaterial: (id: string, title: string, content: string, isInterview = false) =>
    api
      .post(`${BASE}/${id}/materials`, { kind: isInterview ? 'interview' : 'text', title, content })
      .then(r => r.data.data as StudioSession),

  addImageMaterial: (id: string, title: string, assetId: string) =>
    api
      .post(`${BASE}/${id}/materials`, { kind: 'image', title, asset_id: assetId })
      .then(r => r.data.data as StudioSession),

  removeMaterial: (id: string, materialId: string) =>
    api.delete(`${BASE}/${id}/materials/${materialId}`).then(r => r.data.data as StudioSession),

  completeInterview: (id: string) =>
    api.post(`${BASE}/${id}/complete-interview`).then(r => r.data.data as StudioSession),
};
