import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { Plus, Trash2, Loader2, FileInput, Check } from 'lucide-react';
import api, { pages as pagesApi } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

interface WizardField {
  label: string;
  type: 'text' | 'email' | 'textarea' | 'select' | 'checkbox' | 'radio';
  required: boolean;
  placeholder: string;
  options: string[];
}

const FIELD_TYPES: { value: WizardField['type']; label: string }[] = [
  { value: 'text', label: 'Text' },
  { value: 'email', label: 'Email' },
  { value: 'textarea', label: 'Long text' },
  { value: 'select', label: 'Dropdown' },
  { value: 'radio', label: 'Radio choice' },
  { value: 'checkbox', label: 'Checkbox' },
];

/**
 * S5 — Form Wizard: name → fields → notifications, then the platform
 * composes a customform block and appends it to the chosen page.
 */
export default function FormWizardPage() {
  const { siteId = '' } = useParams();
  const { toast } = useToast();

  const [step, setStep] = useState(1);
  const [name, setName] = useState('');
  const [pageId, setPageId] = useState('');
  const [fields, setFields] = useState<WizardField[]>([
    { label: 'Name', type: 'text', required: true, placeholder: '', options: [] },
    { label: 'Email', type: 'email', required: true, placeholder: '', options: [] },
    { label: 'Message', type: 'textarea', required: false, placeholder: '', options: [] },
  ]);
  const [notifyEmail, setNotifyEmail] = useState('');
  const [successMessage, setSuccessMessage] = useState('Thank you! We received your message.');
  const [created, setCreated] = useState<{ form_key: string; page_id: string; page_slug: string } | null>(null);

  const { data: sitePages = [] } = useQuery<{ id: string; title: string; slug: string }[]>({
    queryKey: ['pages', siteId],
    queryFn: () => pagesApi.list(siteId, { per_page: 100 }).then((r) => r.data.data),
  });

  const createMutation = useMutation({
    mutationFn: () => api.post(`/sites/${siteId}/form-wizard`, {
      name,
      page_id: pageId,
      fields,
      notify_email: notifyEmail || null,
      success_message: successMessage,
    }),
    onSuccess: (res) => setCreated(res.data.data),
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message ?? 'Could not create the form.' }),
  });

  const updateField = (i: number, patch: Partial<WizardField>) =>
    setFields(fields.map((f, j) => (j === i ? { ...f, ...patch } : f)));

  if (created) {
    return (
      <div className="max-w-xl mx-auto text-center py-16">
        <div className="w-12 h-12 rounded-full bg-success/10 text-success flex items-center justify-center mx-auto mb-4"><Check /></div>
        <h1 className="text-lg font-semibold mb-2">Form added</h1>
        <p className="text-[13px] text-base-content/60 mb-6">
          “{name}” (key <code className="font-mono">{created.form_key}</code>) is now on the page — publish it to go live.
          Submissions will appear under Site Settings → Forms.
        </p>
        <div className="flex justify-center gap-3">
          <Link to={`/sites/${siteId}/pages/${created.page_id}/edit`} className="btn btn-primary btn-sm text-[12px]">Open the page</Link>
          <button onClick={() => { setCreated(null); setStep(1); setName(''); }} className="btn btn-ghost btn-sm text-[12px]">Create another</button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <h1 className="text-xl font-semibold flex items-center gap-2 mb-1"><FileInput size={20} /> Form Wizard</h1>
      <p className="text-[13px] text-base-content/50 mb-6">
        Build a form and place it on a page — the published page stays static; submissions post to the platform with spam protection built in.
      </p>

      <ul className="steps steps-horizontal w-full mb-6 text-[12px]">
        <li className={`step ${step >= 1 ? 'step-primary' : ''}`}>Form &amp; page</li>
        <li className={`step ${step >= 2 ? 'step-primary' : ''}`}>Fields</li>
        <li className={`step ${step >= 3 ? 'step-primary' : ''}`}>Notifications</li>
      </ul>

      <div className="border border-base-300/40 rounded-box bg-base-100 p-5 space-y-4">
        {step === 1 && (
          <>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Form name</label>
              <input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Artwork inquiry"
                className="input input-bordered input-sm w-full text-[13px]" />
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Add to page</label>
              <select value={pageId} onChange={(e) => setPageId(e.target.value)} className="select select-bordered select-sm w-full text-[13px]">
                <option value="">— pick a page —</option>
                {sitePages.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}
              </select>
              <p className="text-[11px] text-base-content/40 mt-1">The form is appended as a new section; nothing on the page is replaced. To place it on a record template (e.g. every product page), add the Custom Form block there manually.</p>
            </div>
            <div className="flex justify-end">
              <button disabled={!name.trim() || !pageId} onClick={() => setStep(2)} className="btn btn-primary btn-sm text-[12px]">Next</button>
            </div>
          </>
        )}

        {step === 2 && (
          <>
            {fields.map((f, i) => (
              <div key={i} className="border border-base-300/30 rounded p-3 space-y-2">
                <div className="flex items-center gap-2">
                  <input value={f.label} onChange={(e) => updateField(i, { label: e.target.value })}
                    placeholder="Field label" className="input input-bordered input-sm flex-1 text-[13px]" />
                  <select value={f.type} onChange={(e) => updateField(i, { type: e.target.value as WizardField['type'] })}
                    className="select select-bordered select-sm text-[12px]">
                    {FIELD_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                  <label className="flex items-center gap-1 text-[11px] text-base-content/50">
                    <input type="checkbox" className="checkbox checkbox-xs" checked={f.required}
                      onChange={(e) => updateField(i, { required: e.target.checked })} /> req.
                  </label>
                  <button onClick={() => setFields(fields.filter((_, j) => j !== i))} disabled={fields.length === 1}
                    className="btn btn-ghost btn-xs btn-square text-base-content/40"><Trash2 size={12} /></button>
                </div>
                {(f.type === 'select' || f.type === 'radio') && (
                  <input value={f.options.join(', ')}
                    onChange={(e) => updateField(i, { options: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })}
                    placeholder="Options, comma, separated" className="input input-bordered input-sm w-full text-[12px]" />
                )}
              </div>
            ))}
            {fields.length < 20 && (
              <button onClick={() => setFields([...fields, { label: '', type: 'text', required: false, placeholder: '', options: [] }])}
                className="btn btn-ghost btn-xs gap-1 text-[12px] text-primary"><Plus size={13} /> Add field</button>
            )}
            <div className="flex justify-between">
              <button onClick={() => setStep(1)} className="btn btn-ghost btn-sm text-[12px]">Back</button>
              <button disabled={fields.some((f) => !f.label.trim())} onClick={() => setStep(3)} className="btn btn-primary btn-sm text-[12px]">Next</button>
            </div>
          </>
        )}

        {step === 3 && (
          <>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Notification email (optional)</label>
              <input type="email" value={notifyEmail} onChange={(e) => setNotifyEmail(e.target.value)}
                placeholder="you@example.com" className="input input-bordered input-sm w-full text-[13px]" />
              <p className="text-[11px] text-base-content/40 mt-1">Every submission is stored either way and viewable in the admin.</p>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Success message</label>
              <input value={successMessage} onChange={(e) => setSuccessMessage(e.target.value)}
                className="input input-bordered input-sm w-full text-[13px]" />
            </div>
            <div className="flex justify-between">
              <button onClick={() => setStep(2)} className="btn btn-ghost btn-sm text-[12px]">Back</button>
              <button onClick={() => createMutation.mutate()} disabled={createMutation.isPending} className="btn btn-primary btn-sm text-[12px]">
                {createMutation.isPending && <Loader2 size={13} className="animate-spin" />} Create form
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
