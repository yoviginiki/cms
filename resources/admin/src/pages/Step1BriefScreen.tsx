import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Loader2, Sparkles } from 'lucide-react';
import { issueComposer } from '@/lib/api';
import IssueComposerLayout from './IssueComposerLayout';

interface ToneKnobs {
  pace: number;       // 0=contemplative, 100=energetic
  register: number;   // 0=literary, 100=journalistic
  density: number;    // 0=sparse, 100=dense
}

export default function Step1BriefScreen() {
  const { siteId = '', issueId } = useParams();
  const navigate = useNavigate();
  const isEdit = !!issueId;

  // Form state
  const [title, setTitle] = useState('');
  const [subtitle, setSubtitle] = useState('');
  const [theme, setTheme] = useState('');
  const [intention, setIntention] = useState('');
  const [targetReader, setTargetReader] = useState('');
  const [toneKnobs, setToneKnobs] = useState<ToneKnobs>({ pace: 50, register: 50, density: 50 });
  const [targetPageCount, setTargetPageCount] = useState(20);
  const [language, setLanguage] = useState('en');
  const [targetReadingTime, setTargetReadingTime] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Load existing issue if editing
  const { data: issue, isLoading } = useQuery({
    queryKey: ['issue', siteId, issueId],
    queryFn: () => issueComposer.get(siteId, issueId!).then((r: any) => r.data.data),
    enabled: isEdit,
  });

  // Hydrate form from existing issue
  useEffect(() => {
    if (!issue) return;
    setTitle(issue.title || '');
    setSubtitle(issue.subtitle || '');
    setTheme(issue.theme || '');
    setIntention(issue.intention || '');
    setTargetReader(issue.tone_knobs?.target_reader || '');
    setToneKnobs({
      pace: issue.tone_knobs?.pace ?? 50,
      register: issue.tone_knobs?.register ?? 50,
      density: issue.tone_knobs?.density ?? 50,
    });
    setTargetPageCount(issue.target_page_count || 20);
    setLanguage(issue.language || 'en');
    setTargetReadingTime(issue.tone_knobs?.target_reading_time || '');
  }, [issue]);

  // Validate
  const validate = (): boolean => {
    const e: Record<string, string> = {};
    if (!title.trim()) e.title = 'Title is required';
    if (!theme.trim()) e.theme = 'Theme is required';
    if (theme.length > 200) e.theme = 'Theme must be under 200 characters';
    if (!intention.trim()) e.intention = 'Editorial intention is required';
    if (intention.length > 2000) e.intention = 'Intention must be under 2000 characters';
    if (targetPageCount < 8 || targetPageCount > 48) e.targetPageCount = 'Page count must be 8-48';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  // Create
  const createMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => issueComposer.create(siteId, data),
    onSuccess: (r: any) => {
      const id = r.data.data.id;
      navigate(`/sites/${siteId}/issue-composer/${id}/intake`);
    },
  });

  // Update
  const updateMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => issueComposer.update(siteId, issueId!, data),
    onSuccess: () => {
      navigate(`/sites/${siteId}/issue-composer/${issueId}/intake`);
    },
  });

  const handleSubmit = () => {
    if (!validate()) return;

    const data = {
      title: title.trim(),
      subtitle: subtitle.trim() || null,
      theme: theme.trim(),
      intention: intention.trim(),
      tone_knobs: {
        ...toneKnobs,
        target_reader: targetReader.trim() || null,
        target_reading_time: targetReadingTime ? parseInt(targetReadingTime) : null,
      },
      target_page_count: targetPageCount,
      language,
    };

    if (isEdit) {
      updateMutation.mutate(data);
    } else {
      createMutation.mutate(data);
    }
  };

  const isSaving = createMutation.isPending || updateMutation.isPending;
  const saveError = createMutation.error || updateMutation.error;

  if (isLoading) {
    return (
      <IssueComposerLayout currentStep="brief" issueId={issueId} issueStatus={(issue as any)?.status}>
        <div className="flex items-center justify-center h-full"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>
      </IssueComposerLayout>
    );
  }

  return (
    <IssueComposerLayout currentStep="brief" issueId={issueId} issueStatus={(issue as any)?.status}>
      <div className="max-w-2xl mx-auto px-6 py-10">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-2 mb-2">
            <Sparkles size={18} className="text-primary" />
            <h1 className="text-xl font-medium text-base-content/90">Create your issue</h1>
          </div>
          <p className="text-[13px] text-base-content/40">
            Describe what you want this magazine issue to be about. The AI will use this brief to curate content, design the layout, and compose a complete magazine.
          </p>
        </div>

        {saveError && (
          <div className="alert alert-error text-[12px] mb-6">
            {(saveError as any)?.response?.data?.message || 'Failed to save. Please try again.'}
          </div>
        )}

        <div className="space-y-6">
          {/* Title */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Issue title *</label>
            <input value={title} onChange={e => setTitle(e.target.value)}
              className={`input input-bordered w-full text-[14px] ${errors.title ? 'input-error' : ''}`}
              placeholder="e.g. The Spring Issue — Awakening" />
            {errors.title && <p className="text-[11px] text-error mt-1">{errors.title}</p>}
          </div>

          {/* Subtitle */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Subtitle</label>
            <input value={subtitle} onChange={e => setSubtitle(e.target.value)}
              className="input input-bordered w-full text-[14px]"
              placeholder="Optional tagline or description" />
          </div>

          {/* Theme */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">
              Theme * <span className="text-base-content/30 font-normal">— one sentence, what is this issue about?</span>
            </label>
            <input value={theme} onChange={e => setTheme(e.target.value)} maxLength={200}
              className={`input input-bordered w-full text-[14px] ${errors.theme ? 'input-error' : ''}`}
              placeholder="e.g. Exploring the intersection of mindfulness and daily routines" />
            <div className="flex justify-between mt-1">
              {errors.theme ? <p className="text-[11px] text-error">{errors.theme}</p> : <span />}
              <span className="text-[10px] text-base-content/25">{theme.length}/200</span>
            </div>
          </div>

          {/* Editorial intention */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">
              Editorial intention * <span className="text-base-content/30 font-normal">— what should the reader take away?</span>
            </label>
            <textarea value={intention} onChange={e => setIntention(e.target.value)} rows={4} maxLength={2000}
              className={`textarea textarea-bordered w-full text-[14px] ${errors.intention ? 'input-error' : ''}`}
              placeholder="Describe the feeling, message, or experience you want readers to have. What story does this issue tell? What should they learn or feel?" />
            <div className="flex justify-between mt-1">
              {errors.intention ? <p className="text-[11px] text-error">{errors.intention}</p> : <span />}
              <span className="text-[10px] text-base-content/25">{intention.length}/2000</span>
            </div>
          </div>

          {/* Target reader */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Target reader</label>
            <input value={targetReader} onChange={e => setTargetReader(e.target.value)}
              className="input input-bordered w-full text-[14px]"
              placeholder="e.g. Meditation practitioners, ages 25-45, seeking deeper practice" />
          </div>

          {/* Tone sliders */}
          <div>
            <label className="text-[12px] font-medium text-base-content/60 mb-3 block">Tone</label>
            <div className="space-y-4 p-4 bg-base-200/50 rounded-lg">
              {/* Pace */}
              <div>
                <div className="flex justify-between text-[11px] text-base-content/40 mb-1">
                  <span>Contemplative</span>
                  <span>Energetic</span>
                </div>
                <input type="range" min={0} max={100} value={toneKnobs.pace}
                  onChange={e => setToneKnobs({ ...toneKnobs, pace: parseInt(e.target.value) })}
                  className="range range-sm range-primary w-full" />
              </div>

              {/* Register */}
              <div>
                <div className="flex justify-between text-[11px] text-base-content/40 mb-1">
                  <span>Literary</span>
                  <span>Journalistic</span>
                </div>
                <input type="range" min={0} max={100} value={toneKnobs.register}
                  onChange={e => setToneKnobs({ ...toneKnobs, register: parseInt(e.target.value) })}
                  className="range range-sm range-primary w-full" />
              </div>

              {/* Density */}
              <div>
                <div className="flex justify-between text-[11px] text-base-content/40 mb-1">
                  <span>Sparse</span>
                  <span>Dense</span>
                </div>
                <input type="range" min={0} max={100} value={toneKnobs.density}
                  onChange={e => setToneKnobs({ ...toneKnobs, density: parseInt(e.target.value) })}
                  className="range range-sm range-primary w-full" />
              </div>
            </div>
          </div>

          {/* Page count + Language + Reading time */}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Pages</label>
              <input type="number" min={8} max={48} value={targetPageCount}
                onChange={e => setTargetPageCount(parseInt(e.target.value) || 20)}
                className={`input input-bordered w-full text-[14px] ${errors.targetPageCount ? 'input-error' : ''}`} />
              {errors.targetPageCount && <p className="text-[11px] text-error mt-1">{errors.targetPageCount}</p>}
              <p className="text-[10px] text-base-content/25 mt-0.5">8–48 pages</p>
            </div>
            <div>
              <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Language</label>
              <select value={language} onChange={e => setLanguage(e.target.value)}
                className="select select-bordered w-full text-[14px]">
                <option value="en">English</option>
                <option value="bg">Bulgarian</option>
                <option value="de">German</option>
                <option value="fr">French</option>
                <option value="es">Spanish</option>
              </select>
            </div>
            <div>
              <label className="text-[12px] font-medium text-base-content/60 mb-1.5 block">Reading time</label>
              <input type="number" min={1} max={120} value={targetReadingTime}
                onChange={e => setTargetReadingTime(e.target.value)}
                className="input input-bordered w-full text-[14px]"
                placeholder="Optional" />
              <p className="text-[10px] text-base-content/25 mt-0.5">Minutes (optional)</p>
            </div>
          </div>

          {/* Submit */}
          <div className="flex items-center justify-between pt-4 border-t border-base-300/20">
            <button onClick={() => navigate(`/sites/${siteId}/magazines`)}
              className="btn btn-ghost text-[13px]">Cancel</button>
            <button onClick={handleSubmit} disabled={isSaving}
              className="btn btn-primary text-[13px] gap-2">
              {isSaving && <Loader2 size={14} className="animate-spin" />}
              {isEdit ? 'Save & continue to intake' : 'Create issue & continue'}
            </button>
          </div>
        </div>
      </div>
    </IssueComposerLayout>
  );
}
