import { api } from '@/lib/api';
import type { WizardSession, StreamEvent } from './types';

const BASE = '/magazine/wizard/sessions';

export const wizardApi = {
  list: () => api.get(BASE).then(r => r.data.data as WizardSession[]),

  create: (title?: string) =>
    api.post(BASE, { title }).then(r => r.data.data as WizardSession),

  get: (id: string) =>
    api.get(`${BASE}/${id}`).then(r => r.data.data as WizardSession),

  abandon: (id: string) =>
    api.delete(`${BASE}/${id}`),

  lock: (id: string, step: number, locked_artifact: unknown) =>
    api.post(`${BASE}/${id}/lock`, { step, locked_artifact }).then(r => r.data.data as WizardSession),

  unlock: (id: string, to_step: number) =>
    api.post(`${BASE}/${id}/unlock`, { to_step }).then(r => r.data.data as WizardSession),

  provision: (id: string) =>
    api.post(`${BASE}/${id}/provision`).then(r => r.data),
};

/**
 * SSE streaming for sendMessage.
 * Uses native fetch + ReadableStream for real-time text chunks.
 */
export async function streamMessage(
  sessionId: string,
  step: number,
  content: string,
  onEvent: (event: StreamEvent) => void,
): Promise<void> {
  // Get XSRF token for auth
  const xsrf = document.cookie
    .split('; ')
    .find(c => c.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];

  const resp = await fetch(`/api/v1${BASE}/${sessionId}/messages`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/event-stream',
      'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
    },
    body: JSON.stringify({ step, content }),
  });

  if (!resp.ok) {
    // Try to read the error body
    let errorMsg = `HTTP ${resp.status}: ${resp.statusText}`;
    try {
      const body = await resp.text();
      // Could be JSON or SSE with error event
      const json = JSON.parse(body);
      if (json.message) errorMsg = json.message;
    } catch {
      // If body is SSE, try to parse the error event
      try {
        const body = await resp.clone().text();
        const match = body.match(/data:\s*({.*})/);
        if (match) {
          const parsed = JSON.parse(match[1]);
          if (parsed.message) errorMsg = parsed.message;
        }
      } catch { /* ignore */ }
    }
    onEvent({ type: 'error', message: errorMsg });
    return;
  }

  if (!resp.body) {
    onEvent({ type: 'error', message: 'Empty response body' });
    return;
  }

  const reader = resp.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';

    let currentEvent = '';
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith('event: ')) {
        currentEvent = trimmed.slice(7);
      } else if (trimmed.startsWith('data: ')) {
        const data = trimmed.slice(6);
        try {
          const parsed = JSON.parse(data);
          if (currentEvent === 'delta') {
            onEvent({ type: 'delta', text: parsed.text });
          } else if (currentEvent === 'complete') {
            onEvent({
              type: 'complete',
              message_id: parsed.message_id,
              artifact: parsed.artifact,
              tokens_in: parsed.tokens_in,
              tokens_out: parsed.tokens_out,
            });
          } else if (currentEvent === 'error') {
            onEvent({ type: 'error', message: parsed.message });
          }
        } catch {
          // malformed JSON line, skip
        }
        currentEvent = '';
      }
    }
  }
}
