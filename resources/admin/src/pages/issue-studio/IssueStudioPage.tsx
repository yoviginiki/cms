import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import {
  ArrowUp, BookOpen, CheckCircle2, FileText, Image as ImageIcon, Loader2,
  Mic, Sparkles, Trash2, X,
} from 'lucide-react';
import { assets } from '@/lib/api';
import FlatplanBoard from './FlatplanBoard';
import { useStudioStore } from './store';
import { GENRE_LABELS, STATUS_LABELS, type Brief, type Material } from './types';

export default function IssueStudioPage() {
  const { siteId = '', id = '' } = useParams();
  const { session, loading, error, load, reset, clearError } = useStudioStore();

  useEffect(() => {
    load(id);
    return () => reset();
  }, [id, load, reset]);

  if (loading || !session) {
    return (
      <div className="flex items-center justify-center h-[60vh]">
        {error ? (
          <p className="text-[14px] text-error">{error}</p>
        ) : (
          <span className="loading loading-spinner loading-sm text-base-content/20" />
        )}
      </div>
    );
  }

  const interviewing = session.status === 'interviewing';

  return (
    <div className="flex gap-6 h-[calc(100vh-7.5rem)] min-h-[480px]">
      <div className={`flex-1 min-w-0 flex flex-col ${interviewing ? 'border border-base-300' : 'overflow-y-auto'}`}>
        {interviewing ? (
          <>
            <div className="flex items-center gap-2 px-4 py-2.5 border-b border-base-300">
              <Sparkles className="h-4 w-4 text-primary" />
              <span className="text-[14px] font-medium text-base-content/80 truncate">
                {session.title || 'New issue'}
              </span>
              <span className="ml-auto text-[11px] uppercase tracking-wide text-primary border border-primary/40 px-2 py-0.5">
                {STATUS_LABELS[session.status]}
              </span>
            </div>
            <ChatPane siteId={siteId} />
          </>
        ) : (
          <FlatplanBoard />
        )}
      </div>

      <div className="w-[340px] shrink-0 overflow-y-auto">
        <BriefCard brief={session.brief} siteId={siteId} />
      </div>

      {error && (
        <div className="fixed bottom-4 left-1/2 -translate-x-1/2 bg-error text-error-content text-[13px] px-4 py-2 flex items-center gap-3 z-50">
          {error}
          <button onClick={clearError}><X className="h-3.5 w-3.5" /></button>
        </div>
      )}
    </div>
  );
}

function ChatPane({ siteId }: { siteId: string }) {
  const { session, sending, send, addText, addImage, completeInterview } = useStudioStore();
  const [draft, setDraft] = useState('');
  const [dragOver, setDragOver] = useState(false);
  const [uploading, setUploading] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const transcript = session?.transcript ?? [];
  const interviewing = session?.status === 'interviewing';

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, [transcript.length, sending]);

  const submit = () => {
    const text = draft.trim();
    if (!text || sending || !interviewing) return;
    setDraft('');
    // Long pastes become material, not chat noise — ease of use.
    if (text.length > 1500) {
      const title = text.split(/\s+/).slice(0, 6).join(' ') + '…';
      void addText(title, text).then(() =>
        send(`I just added a text to the materials: "${title}" (${text.split(/\s+/).length} words).`),
      );
    } else {
      void send(text);
    }
  };

  const uploadFiles = useCallback(
    async (files: FileList | File[]) => {
      if (!session) return;
      setUploading(true);
      try {
        for (const file of Array.from(files)) {
          if (!file.type.startsWith('image/')) continue;
          const res = await assets.upload(siteId, file);
          const asset = res.data.data ?? res.data;
          await addImage(file.name, asset.id);
        }
        await send('I just uploaded new image(s) to the materials.');
      } finally {
        setUploading(false);
      }
    },
    [session, siteId, addImage, send],
  );

  return (
    <>
      <div
        ref={scrollRef}
        className={`flex-1 overflow-y-auto px-4 py-4 space-y-3 ${dragOver ? 'bg-primary/5' : ''}`}
        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragOver(false);
          if (interviewing && e.dataTransfer.files.length) void uploadFiles(e.dataTransfer.files);
        }}
      >
        {transcript.length === 0 && (
          <div className="text-center py-12">
            <p className="text-[15px] text-base-content/50 mb-1">
              Say hello and tell me what your magazine should be about.
            </p>
            <p className="text-[13px] text-base-content/30">
              One sentence is enough — I'll handle the rest. Drop images anywhere, paste texts right
              into the chat.
            </p>
          </div>
        )}

        {transcript.map((entry, i) => (
          <div key={i} className={`flex ${entry.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div
              className={`max-w-[85%] px-3 py-2 text-[14px] whitespace-pre-wrap leading-relaxed ${
                entry.role === 'user'
                  ? 'bg-primary text-primary-content'
                  : 'bg-base-200 text-base-content/85'
              }`}
            >
              {entry.text}
            </div>
          </div>
        ))}

        {(sending || uploading) && (
          <div className="flex justify-start">
            <div className="bg-base-200 px-3 py-2">
              <Loader2 className="h-4 w-4 animate-spin text-base-content/40" />
            </div>
          </div>
        )}
      </div>

      <div className="border-t border-base-300 p-3">
        {interviewing ? (
          <div className="flex items-end gap-2">
            <textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
              }}
              rows={Math.min(6, Math.max(1, draft.split('\n').length))}
              placeholder="Type here… (paste whole articles too — I'll file them as material)"
              className="textarea textarea-bordered flex-1 text-[14px] leading-snug min-h-0 py-2 resize-none rounded-none"
            />
            <label className="btn btn-ghost btn-sm px-2" title="Upload images">
              <ImageIcon className="h-4 w-4 text-base-content/50" />
              <input
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={(e) => { if (e.target.files?.length) void uploadFiles(e.target.files); e.target.value = ''; }}
              />
            </label>
            <button
              onClick={submit}
              disabled={sending || !draft.trim()}
              className="btn btn-primary btn-sm px-3"
              title="Send"
            >
              <ArrowUp className="h-4 w-4" />
            </button>
          </div>
        ) : (
          <div className="flex items-center gap-2 text-[13px] text-base-content/50 px-1 py-1">
            <CheckCircle2 className="h-4 w-4 text-success" />
            Interview complete — the brief is locked in.
          </div>
        )}
        {interviewing && (session?.brief.topic ?? null) && (
          <button
            onClick={() => void completeInterview()}
            className="mt-2 text-[12px] text-primary hover:underline"
          >
            I'm done — plan the issue with what you have
          </button>
        )}
      </div>
    </>
  );
}

function BriefCard({ brief, siteId }: { brief: Brief; siteId: string }) {
  const { removeMaterial } = useStudioStore();

  const rows: Array<{ label: string; value: string | null }> = [
    { label: 'Topic', value: brief.topic },
    { label: 'Working title', value: brief.working_title },
    { label: 'Audience', value: brief.audience },
    { label: 'Tone', value: brief.tone },
    { label: 'Genre', value: brief.genre ? GENRE_LABELS[brief.genre] : null },
    { label: 'Pages', value: brief.page_ambition ? String(brief.page_ambition) : null },
  ];

  return (
    <div className="border border-base-300">
      <div className="flex items-center gap-2 px-4 py-2.5 border-b border-base-300">
        <BookOpen className="h-4 w-4 text-base-content/40" />
        <span className="text-[13px] font-medium uppercase tracking-wide text-base-content/50">
          The brief
        </span>
      </div>

      <div className="divide-y divide-base-300/60">
        {rows.map((row) => (
          <div key={row.label} className="px-4 py-2.5">
            <div className="text-[11px] uppercase tracking-wide text-base-content/35">{row.label}</div>
            {row.value ? (
              <div className="text-[14px] text-base-content/85 mt-0.5">{row.value}</div>
            ) : (
              <div className="text-[13px] text-base-content/25 italic mt-0.5">Not yet known</div>
            )}
          </div>
        ))}
      </div>

      <div className="border-t border-base-300 px-4 py-2.5">
        <div className="text-[11px] uppercase tracking-wide text-base-content/35 mb-2">
          Materials ({brief.materials.length})
        </div>
        {brief.materials.length === 0 && (
          <p className="text-[13px] text-base-content/25 italic">
            Paste texts into the chat or drop images on it.
          </p>
        )}
        <div className="space-y-1.5">
          {brief.materials.map((m) => (
            <MaterialChip key={m.id} material={m} siteId={siteId} onRemove={() => void removeMaterial(m.id)} />
          ))}
        </div>
      </div>

      {brief.notes.length > 0 && (
        <div className="border-t border-base-300 px-4 py-2.5">
          <div className="text-[11px] uppercase tracking-wide text-base-content/35 mb-1.5">
            Director's notes
          </div>
          <ul className="space-y-1">
            {brief.notes.map((note, i) => (
              <li key={i} className="text-[13px] text-base-content/60 leading-snug">— {note}</li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

function MaterialChip({ material, siteId, onRemove }: { material: Material; siteId: string; onRemove: () => void }) {
  const Icon = material.kind === 'image' ? ImageIcon : material.kind === 'interview' ? Mic : FileText;

  return (
    <div className="flex items-center gap-2 border border-base-300 px-2 py-1.5 group">
      {material.kind === 'image' && material.asset_id ? (
        <img
          src={`/api/v1/sites/${siteId}/assets/${material.asset_id}/serve/thumb`}
          alt={material.title}
          className="h-8 w-8 object-cover shrink-0"
        />
      ) : (
        <Icon className="h-4 w-4 text-base-content/35 shrink-0" />
      )}
      <div className="flex-1 min-w-0">
        <div className="text-[13px] text-base-content/75 truncate">{material.title}</div>
        {material.word_count != null && (
          <div className="text-[11px] text-base-content/35">{material.word_count.toLocaleString()} words</div>
        )}
      </div>
      <button
        onClick={onRemove}
        className="opacity-0 group-hover:opacity-100 text-base-content/30 hover:text-error"
        title="Remove"
      >
        <Trash2 className="h-3.5 w-3.5" />
      </button>
    </div>
  );
}
