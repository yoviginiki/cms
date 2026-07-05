import { create } from 'zustand';
import { studioApi } from './api';
import type { StudioSession } from './types';

interface StudioState {
  session: StudioSession | null;
  loading: boolean;
  sending: boolean;
  error: string | null;

  load: (id: string) => Promise<void>;
  send: (content: string) => Promise<void>;
  addText: (title: string, content: string, isInterview?: boolean) => Promise<void>;
  addImage: (title: string, assetId: string) => Promise<void>;
  removeMaterial: (materialId: string) => Promise<void>;
  completeInterview: () => Promise<void>;
  clearError: () => void;
  reset: () => void;
}

function apiError(e: unknown): string {
  const err = e as { response?: { data?: { error?: string; message?: string } } };
  return err.response?.data?.error || err.response?.data?.message || 'Something went wrong.';
}

export const useStudioStore = create<StudioState>((set, get) => ({
  session: null,
  loading: false,
  sending: false,
  error: null,

  load: async (id) => {
    set({ loading: true, error: null });
    try {
      const session = await studioApi.get(id);
      set({ session, loading: false });
    } catch (e) {
      set({ error: apiError(e), loading: false });
    }
  },

  send: async (content) => {
    const { session } = get();
    if (!session || get().sending) return;

    // optimistic user bubble
    set({
      sending: true,
      error: null,
      session: {
        ...session,
        transcript: [...session.transcript, { role: 'user', text: content, at: new Date().toISOString() }],
      },
    });

    try {
      const updated = await studioApi.sendMessage(session.id, content);
      set({ session: updated, sending: false });
    } catch (e) {
      set({ session, sending: false, error: apiError(e) });
    }
  },

  addText: async (title, content, isInterview = false) => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.addTextMaterial(session.id, title, content, isInterview), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  addImage: async (title, assetId) => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.addImageMaterial(session.id, title, assetId), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  removeMaterial: async (materialId) => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.removeMaterial(session.id, materialId), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  completeInterview: async () => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.completeInterview(session.id), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  clearError: () => set({ error: null }),
  reset: () => set({ session: null, loading: false, sending: false, error: null }),
}));
