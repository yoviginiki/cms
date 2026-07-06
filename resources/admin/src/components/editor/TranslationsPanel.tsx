import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, ExternalLink } from 'lucide-react';
import { pages as pagesApi, posts as postsApi } from '@/lib/api';
import { langMeta, siteLanguages, contentLocale } from '@/lib/languages';

interface SiteLike {
  settings?: { default_language?: string; languages?: string[] } | null;
}

interface TranslationRow {
  locale: string;
  id: string;
  title: string;
  slug: string;
  status: string;
}

interface Props {
  siteId: string;
  contentType: 'pages' | 'posts';
  contentId: string;
  seoMeta: Record<string, unknown> | null | undefined;
  site: SiteLike | null | undefined;
}

/**
 * "Translations" sidebar section: shows this content's language and, for every
 * other site language, a link to the existing translation or a button that
 * creates one (a full copy with the translated slug + locale set).
 */
export const TranslationsPanel: React.FC<Props> = ({ siteId, contentType, contentId, seoMeta, site }) => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const api = contentType === 'pages' ? pagesApi : postsApi;
  const languages = siteLanguages(site);
  const current = contentLocale(seoMeta, site);

  const { data: translations, isLoading } = useQuery({
    queryKey: ['translations', contentType, contentId],
    queryFn: () => api.translations(siteId, contentId).then((r) => r.data.data as TranslationRow[]),
    enabled: languages.length > 1,
  });

  const createMutation = useMutation({
    mutationFn: (locale: string) => api.translate(siteId, contentId, locale),
    onSuccess: (r) => {
      queryClient.invalidateQueries({ queryKey: ['translations', contentType, contentId] });
      queryClient.invalidateQueries({ queryKey: [contentType, siteId] });
      navigate(`/sites/${siteId}/${contentType}/${r.data.data.id}/edit`);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      alert(message || 'Could not create the translation.');
    },
  });

  if (languages.length < 2) {
    return (
      <div className="text-[10px] text-gray-400">
        Enable more languages in Site Settings → Languages to manage translations here.
      </div>
    );
  }

  const byLocale: Record<string, TranslationRow> = {};
  (translations || []).forEach((t) => { byLocale[t.locale] = t; });

  return (
    <div className="space-y-1.5">
      {languages.map((lang) => {
        const meta = langMeta(lang);
        const isCurrent = lang === current;
        const existing = byLocale[lang];

        return (
          <div key={lang}
            className={`flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg border text-[11px] ${
              isCurrent ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'
            }`}>
            <span className="flex items-center gap-1.5 min-w-0">
              <span aria-hidden="true">{meta.flag}</span>
              <span className={`truncate ${isCurrent ? 'font-semibold text-blue-700' : 'text-gray-600'}`}>
                {meta.native}
              </span>
              {isCurrent && <span className="text-[9px] text-blue-400 shrink-0">editing</span>}
              {!isCurrent && existing && (
                <span className={`text-[9px] px-1 rounded shrink-0 ${
                  existing.status === 'published' ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600'
                }`}>{existing.status}</span>
              )}
            </span>
            {!isCurrent && (
              existing ? (
                <button
                  onClick={() => navigate(`/sites/${siteId}/${contentType}/${existing.id}/edit`)}
                  className="inline-flex items-center gap-1 text-blue-500 hover:text-blue-700 shrink-0"
                  title={`Open ${meta.label} version`}>
                  <ExternalLink className="h-3 w-3" /> Open
                </button>
              ) : (
                <button
                  onClick={() => createMutation.mutate(lang)}
                  disabled={createMutation.isPending || isLoading}
                  className="inline-flex items-center gap-1 text-gray-500 hover:text-blue-600 disabled:opacity-40 shrink-0"
                  title={`Create ${meta.label} translation (copies this content)`}>
                  {createMutation.isPending
                    ? <Loader2 className="h-3 w-3 animate-spin" />
                    : <Plus className="h-3 w-3" />} Create
                </button>
              )
            )}
          </div>
        );
      })}
      <p className="text-[9px] text-gray-400 leading-snug">
        "Create" copies this content with the language slug (e.g. <code>about-en</code>) so you can translate it.
        Translations are linked automatically on the published site.
      </p>
    </div>
  );
};
