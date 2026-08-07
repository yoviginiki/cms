import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Blocks, Loader2, Plus, KeyRound, Trash2, Copy, Check, AlertTriangle, Save,
} from 'lucide-react';
import {
  modules,
  type ModuleAccess, type ModuleInfo, type ModuleTokenInfo,
} from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { apiErr } from './collections/shared';

/**
 * Settings → Modules. One screen that adapts to the caller's abilities
 * (returned by the API): platform owners get the global toggle, tenant
 * managers get the per-tenant toggle, settings form, and token manager.
 */
export default function Modules() {
  const { data, isLoading, error } = useQuery<ModuleAccess>({
    queryKey: ['modules'],
    queryFn: () => modules.list().then((r) => r.data),
  });

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6">
        <h1 className="text-xl font-bold text-base-content flex items-center gap-2">
          <Blocks size={18} className="text-base-content/50" /> Modules
        </h1>
        <p className="text-[13px] text-base-content/50">
          Optional capabilities you can switch on per platform and per tenant.
        </p>
      </div>

      {isLoading && (
        <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-base-content/40" /></div>
      )}
      {!!error && (
        <div className="border border-error/30 bg-error/10 rounded-box p-4 text-sm text-error">Failed to load modules.</div>
      )}

      {!isLoading && !error && (
        <div className="space-y-3">
          {(data?.modules ?? []).map((m) => (
            <ModuleCard key={m.key} module={m} abilities={data!.abilities} />
          ))}
          {(data?.modules ?? []).length === 0 && (
            <div className="text-[13px] text-base-content/40 py-10 text-center">No modules registered.</div>
          )}
        </div>
      )}
    </div>
  );
}

function ModuleCard({ module: m, abilities }: { module: ModuleInfo; abilities: ModuleAccess['abilities'] }) {
  const qc = useQueryClient();
  const { toast } = useToast();
  const invalidate = () => qc.invalidateQueries({ queryKey: ['modules'] });

  const globalMut = useMutation({
    mutationFn: (enabled: boolean) => modules.setGlobal(m.key, enabled),
    onSuccess: invalidate,
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const tenantMut = useMutation({
    mutationFn: (enabled: boolean) => modules.setTenant(m.key, enabled),
    onSuccess: invalidate,
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  return (
    <div className="border border-base-300/40 rounded-box bg-base-100">
      <div className="flex items-start gap-3 px-4 py-3.5">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h2 className="text-[14px] font-semibold text-base-content">{m.name}</h2>
            <span className={`badge badge-xs badge-outline text-[10px] ${m.effective_enabled ? 'badge-success' : 'badge-ghost'}`}>
              {m.effective_enabled ? 'active' : 'off'}
            </span>
          </div>
          {m.description && <p className="text-[12px] text-base-content/50 mt-1 leading-relaxed">{m.description}</p>}
        </div>
      </div>

      {/* Toggles */}
      <div className="px-4 pb-3 space-y-2 border-t border-base-300/20 pt-3">
        {abilities.administer && (
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-[12px] text-base-content/70">
              Enabled platform-wide
              <span className="block text-[11px] text-base-content/35">Controls availability for every tenant.</span>
            </span>
            <input
              type="checkbox"
              className="toggle toggle-sm toggle-success"
              checked={m.enabled_globally}
              disabled={globalMut.isPending}
              onChange={(e) => globalMut.mutate(e.target.checked)}
            />
          </label>
        )}

        {abilities.manage && (
          <label className="flex items-center justify-between gap-3 cursor-pointer">
            <span className="text-[12px] text-base-content/70">
              Enabled for this tenant
              {!m.enabled_globally && (
                <span className="block text-[11px] text-warning">Unavailable until enabled platform-wide.</span>
              )}
            </span>
            <input
              type="checkbox"
              className="toggle toggle-sm toggle-success"
              checked={m.enabled_for_tenant}
              disabled={!m.enabled_globally || tenantMut.isPending}
              onChange={(e) => tenantMut.mutate(e.target.checked)}
            />
          </label>
        )}
      </div>

      {/* Settings form + tokens — managers only, module on for the tenant */}
      {abilities.manage && m.enabled_for_tenant && (
        <div className="border-t border-base-300/20">
          {!!m.settings_schema?.fields?.length && <SettingsForm module={m} />}
          <TokenManager moduleKey={m.key} />
        </div>
      )}
    </div>
  );
}

function SettingsForm({ module: m }: { module: ModuleInfo }) {
  const { toast } = useToast();
  const fields = m.settings_schema?.fields ?? [];
  const [values, setValues] = useState<Record<string, unknown>>(() => {
    const init: Record<string, unknown> = {};
    for (const f of fields) init[f.key] = (m.settings?.[f.key] ?? f.default ?? '');
    return init;
  });

  const saveMut = useMutation({
    mutationFn: () => modules.updateSettings(m.key, values),
    onSuccess: () => toast({ type: 'success', message: 'Settings saved.' }),
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  return (
    <div className="px-4 py-3.5 space-y-3">
      <h3 className="text-[11px] uppercase tracking-wider text-base-content/40">Settings</h3>
      {fields.map((f) => (
        <div key={f.key}>
          <label className="text-[11px] text-base-content/50 mb-1 block">{f.label}</label>
          {f.type === 'select' ? (
            <select
              className="select select-bordered select-sm w-full text-[13px]"
              value={String(values[f.key] ?? '')}
              onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
            >
              {(f.options ?? []).map((opt) => <option key={opt} value={opt}>{opt}</option>)}
            </select>
          ) : (
            <input
              className="input input-bordered input-sm w-full text-[13px]"
              value={String(values[f.key] ?? '')}
              onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
            />
          )}
          {f.help && <p className="text-[11px] text-base-content/35 mt-1">{f.help}</p>}
        </div>
      ))}
      <div className="flex justify-end">
        <button
          onClick={() => saveMut.mutate()}
          disabled={saveMut.isPending}
          className="btn btn-primary btn-sm gap-1.5 text-[12px]"
        >
          {saveMut.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />} Save settings
        </button>
      </div>
    </div>
  );
}

function TokenManager({ moduleKey }: { moduleKey: string }) {
  const qc = useQueryClient();
  const { toast } = useToast();
  const [name, setName] = useState('');
  const [newPlaintext, setNewPlaintext] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const [revokeTarget, setRevokeTarget] = useState<ModuleTokenInfo | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['module-tokens', moduleKey],
    queryFn: () => modules.tokens.list(moduleKey).then((r) => r.data.tokens),
  });
  const invalidate = () => qc.invalidateQueries({ queryKey: ['module-tokens', moduleKey] });

  const createMut = useMutation({
    mutationFn: () => modules.tokens.create(moduleKey, { name: name.trim() }),
    onSuccess: (res) => {
      setNewPlaintext(res.data.plaintext);
      setCopied(false);
      setName('');
      invalidate();
      toast({ type: 'success', message: 'Token created.' });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const revokeMut = useMutation({
    mutationFn: (id: string) => modules.tokens.revoke(moduleKey, id),
    onSuccess: () => { setRevokeTarget(null); invalidate(); toast({ type: 'success', message: 'Token revoked.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const copy = async () => {
    if (!newPlaintext) return;
    try {
      await navigator.clipboard.writeText(newPlaintext);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast({ type: 'error', message: 'Copy failed — select the token and copy it manually.' });
    }
  };

  const tokens = data ?? [];

  return (
    <div className="px-4 py-3.5 border-t border-base-300/20 space-y-3">
      <h3 className="text-[11px] uppercase tracking-wider text-base-content/40 flex items-center gap-1.5">
        <KeyRound size={12} /> API tokens
      </h3>

      {newPlaintext && (
        <div className="border border-warning/40 bg-warning/10 rounded-box p-3">
          <div className="flex items-start gap-2.5">
            <AlertTriangle className="h-4 w-4 text-warning mt-0.5 shrink-0" />
            <div className="flex-1 min-w-0">
              <p className="text-[12px] font-medium text-warning mb-1">Token — shown only once</p>
              <p className="text-[11px] text-base-content/60 mb-2">Store it now; it can’t be retrieved again.</p>
              <div className="flex items-center gap-2">
                <code className="text-[12px] font-mono bg-base-200/80 border border-base-300/40 rounded px-2 py-1 truncate">{newPlaintext}</code>
                <button onClick={copy} className="btn btn-ghost btn-xs gap-1 text-[11px] border border-base-300/40 shrink-0">
                  {copied ? <Check size={11} className="text-success" /> : <Copy size={11} />} {copied ? 'Copied' : 'Copy'}
                </button>
              </div>
            </div>
            <button onClick={() => setNewPlaintext(null)} className="btn btn-ghost btn-xs text-[11px]">Done</button>
          </div>
        </div>
      )}

      {/* Create */}
      <div className="flex items-center gap-2">
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Token name (e.g. Culture Engine box)"
          className="input input-bordered input-sm flex-1 text-[13px]"
        />
        <button
          onClick={() => createMut.mutate()}
          disabled={!name.trim() || createMut.isPending}
          className="btn btn-primary btn-sm gap-1.5 text-[12px]"
        >
          {createMut.isPending ? <Loader2 size={13} className="animate-spin" /> : <Plus size={13} />} Create
        </button>
      </div>

      {/* List */}
      {isLoading ? (
        <div className="flex justify-center py-4"><Loader2 className="h-5 w-5 animate-spin text-base-content/30" /></div>
      ) : tokens.length === 0 ? (
        <p className="text-[12px] text-base-content/35">No tokens yet.</p>
      ) : (
        <div className="space-y-1.5">
          {tokens.map((t) => (
            <div key={t.id} className="flex items-center gap-3 border border-base-300/30 rounded px-3 py-2">
              <div className="flex-1 min-w-0">
                <div className="text-[13px] text-base-content font-medium truncate flex items-center gap-2">
                  {t.name}
                  {t.revoked_at && <span className="badge badge-xs badge-error badge-outline text-[10px]">revoked</span>}
                </div>
                <div className="text-[11px] text-base-content/40 flex items-center gap-1.5 mt-0.5">
                  {t.abilities.map((a) => <code key={a} className="text-[10px]">{a}</code>)}
                  <span>· {t.last_used_at ? `used ${new Date(t.last_used_at).toLocaleDateString()}` : 'never used'}</span>
                </div>
              </div>
              {!t.revoked_at && (
                <button
                  onClick={() => setRevokeTarget(t)}
                  className="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error"
                  title="Revoke"
                >
                  <Trash2 size={13} />
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={!!revokeTarget}
        title="Revoke token"
        message={`Revoke “${revokeTarget?.name}”? Any service using it will immediately lose access.`}
        confirmText="Revoke"
        variant="danger"
        onConfirm={() => revokeTarget && revokeMut.mutate(revokeTarget.id)}
        onClose={() => setRevokeTarget(null)}
      />
    </div>
  );
}
