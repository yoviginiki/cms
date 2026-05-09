import { create } from 'zustand';
import type { WizardSession, WizardMessage, StreamEvent } from './types';
import { wizardApi, streamMessage } from './api';

interface WizardState {
  session: WizardSession | null;
  messages: Record<number, WizardMessage[]>; // keyed by step
  currentArtifact: unknown;
  isStreaming: boolean;
  streamingText: string;
  isLoading: boolean;
  error: string | null;
}

interface WizardActions {
  hydrate: (sessionId: string) => Promise<void>;
  sendUserMessage: (text: string) => Promise<void>;
  updateArtifact: (patch: Record<string, unknown>) => void;
  setArtifact: (artifact: unknown) => void;
  lock: () => Promise<void>;
  unlock: (toStep: number) => Promise<void>;
  provision: () => Promise<string | null>;
  autoStartStep: () => void;
  clearError: () => void;
}


export const useWizardStore = create<WizardState & WizardActions>((set, get) => ({
  session: null,
  messages: {},
  currentArtifact: null,
  isStreaming: false,
  streamingText: '',
  isLoading: false,
  error: null,

  clearError: () => set({ error: null }),

  hydrate: async (sessionId) => {
    set({ isLoading: true, error: null });
    try {
      const session = await wizardApi.get(sessionId);
      // Group messages by step
      const grouped: Record<number, WizardMessage[]> = {};
      for (const msg of session.messages || []) {
        if (!grouped[msg.step]) grouped[msg.step] = [];
        grouped[msg.step].push(msg);
      }
      // Set current artifact from the latest assistant message's artifact_update
      const stepMsgs = grouped[session.current_step] || [];
      const lastAssistant = [...stepMsgs].reverse().find(m => m.role === 'assistant' && m.artifact_update);
      set({
        session,
        messages: grouped,
        currentArtifact: lastAssistant?.artifact_update || null,
        isLoading: false,
      });

      // Auto-start: if no messages for the current step, send opening prompt
      if (stepMsgs.length === 0 && session.status === 'active') {
        get().autoStartStep();
      }
    } catch (e: any) {
      set({ error: e.message || 'Failed to load session', isLoading: false });
    }
  },

  autoStartStep: () => {
    const { session } = get();
    if (!session) return;
    const step = session.current_step;
    const openers: Record<number, string> = {
      1: "Let's define the brief for this issue. What is it about — at the level of feeling, not topic?",
      2: "I'm ready to plan the structure. What articles do we have to work with?",
      3: "Which article should we design first?",
      4: "Let's analyze this article. Is it making an argument or telling a story?",
      5: "Time for design directions. Any references you love or hate? How brave are we being — safe, confident, or risky?",
      6: "Let's sketch thumbnail wireframes for the spreads. Ready to go?",
      7: "Here's the full plan. Review everything and provision when ready.",
    };
    const msg = openers[step];
    if (msg) get().sendUserMessage(msg);
  },

  sendUserMessage: async (text) => {
    const { session } = get();
    if (!session) return;

    const step = session.current_step;

    // Optimistically add user message
    const optimisticMsg: WizardMessage = {
      id: `temp-${Date.now()}`,
      step,
      role: 'user',
      content: text,
      artifact_update: null,
      tokens_in: null,
      tokens_out: null,
      created_at: new Date().toISOString(),
    };

    set(state => ({
      messages: {
        ...state.messages,
        [step]: [...(state.messages[step] || []), optimisticMsg],
      },
      isStreaming: true,
      streamingText: '',
      error: null,
    }));

    try {
      await streamMessage(session.id, step, text, (event: StreamEvent) => {
        if (event.type === 'delta') {
          set(state => ({ streamingText: state.streamingText + event.text }));
        } else if (event.type === 'complete') {
          const assistantMsg: WizardMessage = {
            id: event.message_id,
            step,
            role: 'assistant',
            content: get().streamingText,
            artifact_update: event.artifact as Record<string, unknown> | null,
            tokens_in: event.tokens_in,
            tokens_out: event.tokens_out,
            created_at: new Date().toISOString(),
          };

          set(state => ({
            messages: {
              ...state.messages,
              [step]: [...(state.messages[step] || []), assistantMsg],
            },
            isStreaming: false,
            streamingText: '',
            currentArtifact: event.artifact || state.currentArtifact,
          }));
        } else if (event.type === 'error') {
          set({ isStreaming: false, streamingText: '', error: event.message });
        }
      });
    } catch (e: any) {
      set({ isStreaming: false, streamingText: '', error: e.message || 'Connection failed' });
    }

    // Safety: if still streaming after the call returned (no complete/error event), reset
    if (get().isStreaming) {
      set({ isStreaming: false, error: 'No response received. Check your AI key in Settings → AI.' });
    }
  },

  updateArtifact: (patch) => {
    set(state => ({
      currentArtifact: state.currentArtifact
        ? { ...(state.currentArtifact as Record<string, unknown>), ...patch }
        : patch,
    }));
  },

  setArtifact: (artifact) => set({ currentArtifact: artifact }),

  lock: async () => {
    const { session, currentArtifact } = get();
    if (!session || !currentArtifact) return;

    try {
      const updated = await wizardApi.lock(session.id, session.current_step, currentArtifact);
      set({
        session: updated,
        currentArtifact: null,
        messages: { ...get().messages },
      });

      // Auto-start the next step's conversation
      const nextStepMsgs = get().messages[updated.current_step] || [];
      if (nextStepMsgs.length === 0 && updated.status === 'active' && updated.current_step <= 7) {
        setTimeout(() => get().autoStartStep(), 300);
      }
    } catch (e: any) {
      set({ error: e.response?.data?.message || 'Failed to lock step' });
    }
  },

  unlock: async (toStep) => {
    const { session } = get();
    if (!session) return;

    try {
      const updated = await wizardApi.unlock(session.id, toStep);
      set({
        session: updated,
        currentArtifact: null,
      });
    } catch (e: any) {
      set({ error: e.response?.data?.message || 'Failed to unlock' });
    }
  },

  provision: async () => {
    const { session } = get();
    if (!session) return null;

    try {
      const result = await wizardApi.provision(session.id);
      return result.issue_id || null;
    } catch (e: any) {
      set({ error: e.response?.data?.message || 'Provisioning not implemented yet' });
      return null;
    }
  },
}));
