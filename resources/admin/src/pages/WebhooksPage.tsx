import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import {
  Webhook as WebhookIcon, Plus, Loader2, Trash2, Pencil, Send, ChevronDown, ChevronUp,
  Copy, Check, AlertTriangle,
} from 'lucide-react';
import {
  webhooks,
  type Webhook, type WebhookDelivery, type WebhookEvent,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Modal, apiErr, validationErrors } from './collections/shared';

const ALL_EVENTS: { value: WebhookEvent; label: string }[] = [
  { value: 'record.created', label: 'Record created' },
  { value: 'record.updated', label: 'Record updated' },
  { value: 'record.deleted', label: 'Record deleted' },
  { value: 'form.submitted', label: 'Form submitted' },
];

function statusDot(hook: Webhook): { cls: string; label: string } {
  if (!hook.last_delivered_at) return { cls: 'bg-base-content/20', label: 'No deliveries yet' };
  if (hook.last_status && hook.last_status >= 200 && hook.last_status < 300) {
    return { cls: 'bg-success', label: `Last delivery OK (${hook.last_status})` };
  }
  return { cls: 'bg-error', label: `Last delivery failed (${hook.last_status ?? 'no response'})` };
}

export default function WebhooksPage() {
  const { siteId = '' } = useParams();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Webhook | null>(null);
  const [url, setUrl] = useState('');
  const [events, setEvents] = useState<WebhookEvent[]>([]);
  const [active, setActive] = useState(true);
  const [formError, setFormError] = useState('');
  const [deleteTarget, setDeleteTarget] = useState<Webhook | null>(null);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [newSecret, setNewSecret] = useState<{ id: string; secret: string } | null>(null);
  const [copied, setCopied] = useState(false);

  const { data: hooks = [], isLoading, error } = useQuery<Webhook[]>({
    queryKey: ['webhooks', siteId],
    queryFn: () => webhooks.list(siteId).then((r) => r.data.data),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['webhooks', siteId] });

  const openCreate = () => {
    setEditing(null);
    setUrl('');
    setEvents(['record.created', 'record.updated', 'record.deleted']);
    setActive(true);
    setFormError('');
    setModalOpen(true);
  };

  const openEdit = (hook: Webhook) => {
    setEditing(hook);
    setUrl(hook.url);
    setEvents(hook.events);
    setActive(hook.active);
    setFormError('');
    setModalOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: () =>
      editing
        ? webhooks.update(siteId, editing.id, { url: url.trim(), events, active })
        : webhooks.create(siteId, { url: url.trim(), events, active }),
    onSuccess: (res, _vars) => {
      invalidate();
      setModalOpen(false);
      if (!editing && res.data.data.secret) {
        setNewSecret({ id: res.data.data.id, secret: res.data.data.secret });
        setCopied(false);
      }
      toast({ type: 'success', message: editing ? 'Webhook updated.' : 'Webhook created.' });
    },
    onError: (e: any) => {
      const errs = validationErrors(e);
      setFormError(Object.values(errs)[0] ?? apiErr(e));
    },
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, next }: { id: string; next: boolean }) => webhooks.update(siteId, id, { active: next }),
    onSuccess: invalidate,
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => webhooks.delete(siteId, id),
    onSuccess: () => {
      invalidate();
      setDeleteTarget(null);
      toast({ type: 'success', message: 'Webhook deleted.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const testMutation = useMutation({
    mutationFn: (id: string) => webhooks.test(siteId, id),
    onSuccess: () => {
      invalidate();
      queryClient.invalidateQueries({ queryKey: ['webhook-deliveries', siteId] });
      toast({ type: 'success', message: 'Test delivery queued — check the recent deliveries.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const copySecret = async () => {
    if (!newSecret) return;
    try {
      await navigator.clipboard.writeText(newSecret.secret);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast({ type: 'error', message: 'Copy failed — select the secret and copy it manually.' });
    }
  };

  const toggleEvent = (ev: WebhookEvent) =>
    setEvents((prev) => (prev.includes(ev) ? prev.filter((x) => x !== ev) : [...prev, ev]));

  const canSave = /^https?:\/\/\S+/.test(url.trim()) && events.length > 0;

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="flex-1">
          <h1 className="text-xl font-bold text-base-content flex items-center gap-2">
            <WebhookIcon size={18} className="text-base-content/50" /> Webhooks
          </h1>
          <p className="text-[13px] text-base-content/50">Notify external systems when records change or forms are submitted.</p>
        </div>
        <button onClick={openCreate} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
          <Plus size={14} /> New webhook
        </button>
      </div>

      {/* One-time secret callout */}
      {newSecret && (
        <div className="border border-warning/40 bg-warning/10 rounded-box p-4 mb-5">
          <div className="flex items-start gap-2.5">
            <AlertTriangle className="h-4 w-4 text-warning mt-0.5 shrink-0" />
            <div className="flex-1 min-w-0">
              <p className="text-[13px] font-medium text-warning mb-1">Signing secret — shown only once</p>
              <p className="text-[12px] text-base-content/60 mb-2">
                Store it now; it can’t be retrieved again. Use it to verify the <code className="text-[11px]">X-Webhook-Signature</code> header.
              </p>
              <div className="flex items-center gap-2">
                <code className="text-[12px] font-mono bg-base-200/80 border border-base-300/40 rounded px-2 py-1 truncate">{newSecret.secret}</code>
                <button onClick={copySecret} className="btn btn-ghost btn-xs gap-1 text-[11px] border border-base-300/40 shrink-0">
                  {copied ? <Check size={11} className="text-success" /> : <Copy size={11} />} {copied ? 'Copied' : 'Copy'}
                </button>
              </div>
            </div>
            <button onClick={() => setNewSecret(null)} className="btn btn-ghost btn-xs text-[11px]">Done</button>
          </div>
        </div>
      )}

      {isLoading && <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>}
      {!!error && <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load webhooks.</div>}

      {!isLoading && !error && hooks.length === 0 && (
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <WebhookIcon className="h-10 w-10 text-base-content/15 mb-4" strokeWidth={1.5} />
          <h3 className="text-sm font-medium text-base-content/60 mb-1">No webhooks yet</h3>
          <p className="text-[13px] text-base-content/35 mb-6 max-w-sm">
            Point a URL at Zapier, Make, or your own service and we’ll POST a signed payload on every subscribed event.
          </p>
          <button onClick={openCreate} className="btn btn-primary btn-sm gap-1.5 text-[12px]">
            <Plus size={13} /> Add a webhook
          </button>
        </div>
      )}

      {/* Hook list */}
      <div className="space-y-2.5">
        {hooks.map((hook) => {
          const dot = statusDot(hook);
          const expanded = expandedId === hook.id;
          return (
            <div key={hook.id} className="border border-base-300/40 rounded-box bg-base-100">
              <div className="flex items-center gap-3 px-4 py-3">
                <span className={`w-2 h-2 rounded-full shrink-0 ${dot.cls}`} title={dot.label} />
                <div className="flex-1 min-w-0">
                  <div className="text-[13px] font-medium text-base-content font-mono truncate">{hook.url}</div>
                  <div className="flex items-center gap-1 mt-1 flex-wrap">
                    {hook.events.map((ev) => (
                      <span key={ev} className="badge badge-ghost badge-xs text-[10px]">{ev}</span>
                    ))}
                    <span className="text-[11px] text-base-content/35 ml-1">
                      {hook.last_delivered_at
                        ? `last delivery ${new Date(hook.last_delivered_at).toLocaleString()}${hook.last_status ? ` · ${hook.last_status}` : ''}`
                        : 'never delivered'}
                    </span>
                  </div>
                </div>
                <label className="flex items-center gap-1.5 cursor-pointer" title={hook.active ? 'Active' : 'Paused'}>
                  <input
                    type="checkbox"
                    className="toggle toggle-xs toggle-success"
                    checked={hook.active}
                    onChange={(e) => toggleMutation.mutate({ id: hook.id, next: e.target.checked })}
                  />
                </label>
                <button
                  onClick={() => testMutation.mutate(hook.id)}
                  disabled={testMutation.isPending}
                  className="btn btn-ghost btn-xs gap-1 text-[11px]" title="Send a test delivery"
                >
                  {testMutation.isPending && testMutation.variables === hook.id
                    ? <Loader2 size={12} className="animate-spin" /> : <Send size={12} />}
                  Test
                </button>
                <button onClick={() => openEdit(hook)} className="btn btn-ghost btn-xs btn-square" title="Edit"><Pencil size={13} /></button>
                <button onClick={() => setDeleteTarget(hook)} className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error" title="Delete"><Trash2 size={13} /></button>
                <button
                  onClick={() => setExpandedId(expanded ? null : hook.id)}
                  className="btn btn-ghost btn-xs btn-square text-base-content/40"
                  title="Recent deliveries"
                >
                  {expanded ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
                </button>
              </div>
              {expanded && <DeliveriesTable siteId={siteId} webhookId={hook.id} />}
            </div>
          );
        })}
      </div>

      {/* Create / edit modal */}
      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Edit webhook' : 'New webhook'}>
        <div className="space-y-4">
          {formError && <div className="border border-error/30 bg-error/10 rounded-box px-3 py-2 text-[12px] text-error">{formError}</div>}
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Endpoint URL</label>
            <input
              autoFocus
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="https://example.com/hooks/cms"
              className="input input-bordered input-sm w-full text-[13px] font-mono"
            />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1.5 block">Events</label>
            <div className="space-y-1.5">
              {ALL_EVENTS.map(({ value, label }) => (
                <label key={value} className="flex items-center gap-2.5 text-[13px] cursor-pointer">
                  <input
                    type="checkbox"
                    className="checkbox checkbox-xs"
                    checked={events.includes(value)}
                    onChange={() => toggleEvent(value)}
                  />
                  <span className="text-base-content/80">{label}</span>
                  <code className="text-[11px] text-base-content/35">{value}</code>
                </label>
              ))}
            </div>
            {events.length === 0 && <p className="text-[11px] text-warning mt-1">Pick at least one event.</p>}
          </div>
          <label className="flex items-center gap-2.5 cursor-pointer w-fit">
            <input type="checkbox" className="toggle toggle-sm toggle-success" checked={active} onChange={(e) => setActive(e.target.checked)} />
            <span className="text-[13px] text-base-content/60">{active ? 'Active' : 'Paused'}</span>
          </label>
          <div className="flex justify-end gap-2 pt-1">
            <button onClick={() => setModalOpen(false)} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
            <button
              onClick={() => saveMutation.mutate()}
              disabled={!canSave || saveMutation.isPending}
              className="btn btn-primary btn-sm gap-1.5 text-[12px]"
            >
              {saveMutation.isPending && <Loader2 size={13} className="animate-spin" />}
              {editing ? 'Save changes' : 'Create webhook'}
            </button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        title="Delete webhook"
        message={`Stop delivering to ${deleteTarget?.url}? External systems relying on it will no longer be notified.`}
        confirmText="Delete"
        variant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onClose={() => setDeleteTarget(null)}
      />
    </div>
  );
}

// ── Recent deliveries (lazy — fetched when a hook row is expanded) ──
function DeliveriesTable({ siteId, webhookId }: { siteId: string; webhookId: string }) {
  const { data: deliveries = [], isLoading, error } = useQuery<WebhookDelivery[]>({
    queryKey: ['webhook-deliveries', siteId, webhookId],
    queryFn: () => webhooks.deliveries(siteId, webhookId).then((r) => r.data.data),
  });

  if (isLoading) {
    return <div className="flex justify-center py-6 border-t border-base-300/20"><Loader2 className="h-5 w-5 animate-spin text-base-content/30" /></div>;
  }
  if (error) {
    return <p className="px-4 py-3 border-t border-base-300/20 text-[12px] text-error">Failed to load deliveries.</p>;
  }
  if (deliveries.length === 0) {
    return <p className="px-4 py-3 border-t border-base-300/20 text-[12px] text-base-content/35">No deliveries yet.</p>;
  }

  return (
    <div className="border-t border-base-300/20 overflow-x-auto">
      <table className="table table-xs">
        <thead>
          <tr className="border-b border-base-300/30">
            <th className="text-[10px] uppercase tracking-wider text-base-content/40">Event</th>
            <th className="text-[10px] uppercase tracking-wider text-base-content/40">Status</th>
            <th className="text-[10px] uppercase tracking-wider text-base-content/40">Attempts</th>
            <th className="text-[10px] uppercase tracking-wider text-base-content/40">Response</th>
            <th className="text-[10px] uppercase tracking-wider text-base-content/40">When</th>
          </tr>
        </thead>
        <tbody>
          {deliveries.map((d) => (
            <tr key={d.id} className="border-b border-base-300/10">
              <td><code className="text-[11px]">{d.event}</code></td>
              <td>
                <span className={`badge badge-xs badge-outline text-[10px] ${
                  d.status === 'delivered' || d.status === 'success' ? 'badge-success'
                    : d.status === 'pending' || d.status === 'retrying' ? 'badge-warning' : 'badge-error'
                }`}>
                  {d.status}
                </span>
              </td>
              <td className="text-[11px] tabular-nums">{d.attempts}</td>
              <td className="text-[11px] tabular-nums">{d.response_code ?? '—'}</td>
              <td className="text-[11px] text-base-content/50 whitespace-nowrap">{new Date(d.created_at).toLocaleString()}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
