import { create } from 'zustand';
import { studioApi } from './api';
import type { StudioSession } from './types';

interface StudioState {
  session: StudioSession | null;
  loading: boolean;
  sending: boolean;
  planning: boolean;
  revisingPosition: number | null;
  error: string | null;

  load: (id: string) => Promise<void>;
  send: (content: string) => Promise<void>;
  addText: (title: string, content: string, isInterview?: boolean) => Promise<void>;
  addImage: (title: string, assetId: string) => Promise<void>;
  removeMaterial: (materialId: string) => Promise<void>;
  completeInterview: () => Promise<void>;
  setAutoSourceImages: (enabled: boolean) => Promise<void>;
  generateFlatplan: () => Promise<void>;
  reviseSpread: (position: number, instruction: string) => Promise<void>;
  reorder: (order: number[]) => Promise<void>;
  approveFlatplan: () => Promise<void>;
  generatingSpread: boolean;
  generateNextSpread: () => Promise<void>;
  keepSpread: (position: number) => Promise<void>;
  reviseGeneratedSpread: (position: number, instruction: string) => Promise<void>;
  rethinkSpread: (position: number, pattern?: string) => Promise<void>;
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
  planning: false,
  revisingPosition: null,
  generatingSpread: false,
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

  setAutoSourceImages: async (enabled) => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.setAutoSourceImages(session.id, enabled), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  generateFlatplan: async () => {
    const { session } = get();
    if (!session || get().planning) return;
    set({ planning: true, error: null });
    try {
      set({ session: await studioApi.generateFlatplan(session.id), planning: false });
    } catch (e) {
      set({ planning: false, error: apiError(e) });
    }
  },

  reviseSpread: async (position, instruction) => {
    const { session } = get();
    if (!session || get().revisingPosition !== null) return;
    set({ revisingPosition: position, error: null });
    try {
      set({ session: await studioApi.reviseFlatplanSpread(session.id, position, instruction), revisingPosition: null });
    } catch (e) {
      set({ revisingPosition: null, error: apiError(e) });
    }
  },

  reorder: async (order) => {
    const { session } = get();
    if (!session?.flatplan) return;

    // optimistic local reorder
    const byPos = new Map(session.flatplan.spreads.map((s) => [s.position, s]));
    const reordered = order.map((oldPos, newPos) => ({ ...byPos.get(oldPos)!, position: newPos }));
    set({ session: { ...session, flatplan: { ...session.flatplan, spreads: reordered } } });

    try {
      set({ session: await studioApi.reorderFlatplan(session.id, order), error: null });
    } catch (e) {
      set({ session, error: apiError(e) });
    }
  },

  approveFlatplan: async () => {
    const { session } = get();
    if (!session) return;
    set({ error: null });
    try {
      set({ session: await studioApi.approveFlatplan(session.id) });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  generateNextSpread: async () => {
    const { session } = get();
    if (!session || get().generatingSpread) return;
    set({ generatingSpread: true, error: null });
    try {
      set({ session: await studioApi.generateNextSpread(session.id), generatingSpread: false });
    } catch (e) {
      set({ generatingSpread: false, error: apiError(e) });
    }
  },

  keepSpread: async (position) => {
    const { session } = get();
    if (!session) return;
    try {
      set({ session: await studioApi.keepSpread(session.id, position), error: null });
    } catch (e) {
      set({ error: apiError(e) });
    }
  },

  reviseGeneratedSpread: async (position, instruction) => {
    const { session } = get();
    if (!session || get().generatingSpread) return;
    set({ generatingSpread: true, error: null });
    try {
      set({ session: await studioApi.reviseSpread(session.id, position, instruction), generatingSpread: false });
    } catch (e) {
      set({ generatingSpread: false, error: apiError(e) });
    }
  },

  rethinkSpread: async (position, pattern) => {
    const { session } = get();
    if (!session || get().generatingSpread) return;
    set({ generatingSpread: true, error: null });
    try {
      set({ session: await studioApi.rethinkSpread(session.id, position, pattern), generatingSpread: false });
    } catch (e) {
      set({ generatingSpread: false, error: apiError(e) });
    }
  },

  clearError: () => set({ error: null }),
  reset: () => set({ session: null, loading: false, sending: false, planning: false, revisingPosition: null, generatingSpread: false, error: null }),
}));
