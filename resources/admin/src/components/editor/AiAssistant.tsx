import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Sparkles, Loader2, Check, X, Languages, Wand2 } from 'lucide-react';
import { ai } from '@/lib/api';
import { useEditorStore } from '@/stores/editorStore';

interface Props {
  blockId: string;
  blockType: string;
  blockData: Record<string, unknown>;
}

export function AiAssistant({ blockId, blockType, blockData }: Props) {
  const [showMenu, setShowMenu] = useState(false);
  const [showPrompt, setShowPrompt] = useState(false);
  const [prompt, setPrompt] = useState('');
  const [suggestion, setSuggestion] = useState<string | null>(null);
  const [showTranslate, setShowTranslate] = useState(false);
  const updateBlock = useEditorStore((s) => s.updateBlock);

  const contentField = ['heading', 'hero'].includes(blockType) ? 'text'
    : blockType === 'ctabanner' ? 'heading'
    : blockType === 'pullquote' ? 'quote'
    : 'content';

  const generateMutation = useMutation({
    mutationFn: (p: string) => ai.generate(p),
    onSuccess: (res) => {
      setSuggestion(res.data?.data?.content || '');
      setShowPrompt(false);
    },
  });

  const rewriteMutation = useMutation({
    mutationFn: (instruction: string) => ai.rewrite(
      (blockData[contentField] as string) || (blockData.content as string) || (blockData.text as string) || '',
      instruction,
    ),
    onSuccess: (res) => {
      setSuggestion(res.data?.data?.content || '');
    },
  });

  const translateMutation = useMutation({
    mutationFn: (lang: string) => ai.translate(
      (blockData[contentField] as string) || (blockData.content as string) || (blockData.text as string) || '',
      lang,
    ),
    onSuccess: (res) => {
      setSuggestion(res.data?.data?.content || '');
      setShowTranslate(false);
    },
  });

  const isLoading = generateMutation.isPending || rewriteMutation.isPending || translateMutation.isPending;

  const acceptSuggestion = () => {
    if (!suggestion) return;
    updateBlock(blockId, { [contentField]: suggestion });
    setSuggestion(null);
  };

  if (!['text', 'heading', 'paragraph', 'rich-text', 'ctabanner', 'hero', 'caption', 'pullquote'].includes(blockType)) return null;

  return (
    <div className="relative">
      <button
        onClick={() => setShowMenu(!showMenu)}
        className="flex items-center gap-1 px-2 py-1 text-xs text-primary hover:bg-primary/5 rounded transition-colors"
        title="AI Assistant"
      >
        <Sparkles size={12} />
        AI
      </button>

      {/* Suggestion overlay */}
      {suggestion && (
        <div className="absolute top-full right-0 mt-1 w-80 bg-base-100 border border-primary/20 rounded-lg shadow-xl z-50 p-3">
          <p className="text-xs font-medium text-primary mb-2">AI Suggestion</p>
          <div className="text-sm text-base-content/70 max-h-40 overflow-y-auto mb-3 prose prose-sm" dangerouslySetInnerHTML={{ __html: suggestion }} />
          <div className="flex gap-2">
            <button onClick={acceptSuggestion} className="btn btn-primary btn-xs gap-1">
              <Check size={12} /> Accept
            </button>
            <button onClick={() => setSuggestion(null)} className="btn btn-ghost btn-xs gap-1">
              <X size={12} /> Discard
            </button>
          </div>
        </div>
      )}

      {/* Menu dropdown */}
      {showMenu && !suggestion && (
        <div className="absolute top-full right-0 mt-1 w-48 bg-base-100 border border-base-300/30 rounded-lg shadow-xl z-50 py-1">
          {isLoading ? (
            <div className="flex items-center gap-2 px-3 py-4 text-sm text-base-content/40">
              <Loader2 size={14} className="animate-spin" /> Generating...
            </div>
          ) : (
            <>
              <button onClick={() => { setShowPrompt(true); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200 flex items-center gap-2 text-base-content/80">
                <Sparkles size={14} /> Generate
              </button>
              <div className="border-t border-base-300/20 my-1" />
              <p className="px-3 py-1 text-xs text-base-content/30">Rewrite</p>
              {['shorter', 'longer', 'simpler', 'more formal', 'more direct', 'fix grammar'].map(opt => (
                <button key={opt} onClick={() => { rewriteMutation.mutate(opt); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200 capitalize text-base-content/70">
                  {opt}
                </button>
              ))}
              <div className="border-t border-base-300/20 my-1" />
              <button onClick={() => { setShowTranslate(true); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200 flex items-center gap-2 text-base-content/80">
                <Languages size={14} /> Translate
              </button>
            </>
          )}
        </div>
      )}

      {/* Generate prompt */}
      {showPrompt && (
        <div className="absolute top-full right-0 mt-1 w-72 bg-base-100 border border-base-300/30 rounded-lg shadow-xl z-50 p-3">
          <p className="text-xs font-medium text-base-content/70 mb-2">What should I write?</p>
          <textarea
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            placeholder="e.g. Write an introduction about..."
            className="textarea textarea-bordered w-full text-sm resize-none"
            rows={3}
            autoFocus
          />
          <div className="flex justify-end gap-2 mt-2">
            <button onClick={() => setShowPrompt(false)} className="btn btn-ghost btn-xs">Cancel</button>
            <button
              onClick={() => generateMutation.mutate(prompt)}
              disabled={!prompt.trim() || generateMutation.isPending}
              className="btn btn-primary btn-xs gap-1"
            >
              {generateMutation.isPending ? <Loader2 size={12} className="animate-spin" /> : <Wand2 size={12} />}
              Generate
            </button>
          </div>
        </div>
      )}

      {/* Translate picker */}
      {showTranslate && (
        <div className="absolute top-full right-0 mt-1 w-48 bg-base-100 border border-base-300/30 rounded-lg shadow-xl z-50 py-1">
          <p className="px-3 py-1 text-xs text-base-content/30">Translate to</p>
          {['English', 'Bulgarian', 'German', 'French', 'Spanish', 'Italian', 'Portuguese', 'Japanese', 'Chinese'].map(lang => (
            <button key={lang} onClick={() => translateMutation.mutate(lang)} className="w-full text-left px-3 py-1.5 text-sm hover:bg-base-200 text-base-content/70">
              {lang}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
