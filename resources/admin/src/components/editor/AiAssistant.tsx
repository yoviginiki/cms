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

  const generateMutation = useMutation({
    mutationFn: (p: string) => ai.generate(p),
    onSuccess: (res) => {
      setSuggestion(res.data?.data?.content || '');
      setShowPrompt(false);
    },
  });

  const rewriteMutation = useMutation({
    mutationFn: (instruction: string) => ai.rewrite(
      (blockData.content as string) || (blockData.text as string) || '',
      instruction,
    ),
    onSuccess: (res) => {
      setSuggestion(res.data?.data?.content || '');
    },
  });

  const translateMutation = useMutation({
    mutationFn: (lang: string) => ai.translate(
      (blockData.content as string) || (blockData.text as string) || '',
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
    const field = blockType === 'heading' ? 'text' : 'content';
    updateBlock(blockId, { [field]: suggestion });
    setSuggestion(null);
  };

  if (!['text', 'heading'].includes(blockType)) return null;

  return (
    <div className="relative">
      <button
        onClick={() => setShowMenu(!showMenu)}
        className="flex items-center gap-1 px-2 py-1 text-xs text-purple-600 hover:bg-purple-50 rounded transition-colors"
        title="AI Assistant"
      >
        <Sparkles size={12} />
        AI
      </button>

      {/* Suggestion overlay */}
      {suggestion && (
        <div className="absolute top-full right-0 mt-1 w-80 bg-white border border-purple-200 rounded-lg shadow-xl z-50 p-3">
          <p className="text-xs font-medium text-purple-700 mb-2">AI Suggestion</p>
          <div className="text-sm text-gray-700 max-h-40 overflow-y-auto mb-3 prose prose-sm" dangerouslySetInnerHTML={{ __html: suggestion }} />
          <div className="flex gap-2">
            <button onClick={acceptSuggestion} className="flex items-center gap-1 px-3 py-1 bg-purple-600 text-white text-xs rounded-md hover:bg-purple-700">
              <Check size={12} /> Accept
            </button>
            <button onClick={() => setSuggestion(null)} className="flex items-center gap-1 px-3 py-1 border border-gray-300 text-xs rounded-md hover:bg-gray-50">
              <X size={12} /> Discard
            </button>
          </div>
        </div>
      )}

      {/* Menu dropdown */}
      {showMenu && !suggestion && (
        <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-xl z-50 py-1">
          {isLoading ? (
            <div className="flex items-center gap-2 px-3 py-4 text-sm text-gray-500">
              <Loader2 size={14} className="animate-spin" /> Generating...
            </div>
          ) : (
            <>
              <button onClick={() => { setShowPrompt(true); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                <Sparkles size={14} /> Generate
              </button>
              <div className="border-t border-gray-100 my-1" />
              <p className="px-3 py-1 text-xs text-gray-400">Rewrite</p>
              {['shorter', 'longer', 'simpler', 'more formal', 'fix grammar'].map(opt => (
                <button key={opt} onClick={() => { rewriteMutation.mutate(opt); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 capitalize">
                  {opt}
                </button>
              ))}
              <div className="border-t border-gray-100 my-1" />
              <button onClick={() => { setShowTranslate(true); setShowMenu(false); }} className="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                <Languages size={14} /> Translate
              </button>
            </>
          )}
        </div>
      )}

      {/* Generate prompt */}
      {showPrompt && (
        <div className="absolute top-full right-0 mt-1 w-72 bg-white border border-gray-200 rounded-lg shadow-xl z-50 p-3">
          <p className="text-xs font-medium text-gray-700 mb-2">What should I write?</p>
          <textarea
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            placeholder="e.g. Write an introduction about..."
            className="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-md resize-none"
            rows={3}
            autoFocus
          />
          <div className="flex justify-end gap-2 mt-2">
            <button onClick={() => setShowPrompt(false)} className="px-3 py-1 text-xs border border-gray-300 rounded-md">Cancel</button>
            <button
              onClick={() => generateMutation.mutate(prompt)}
              disabled={!prompt.trim() || generateMutation.isPending}
              className="flex items-center gap-1 px-3 py-1 bg-purple-600 text-white text-xs rounded-md hover:bg-purple-700 disabled:opacity-50"
            >
              {generateMutation.isPending ? <Loader2 size={12} className="animate-spin" /> : <Wand2 size={12} />}
              Generate
            </button>
          </div>
        </div>
      )}

      {/* Translate picker */}
      {showTranslate && (
        <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-xl z-50 py-1">
          <p className="px-3 py-1 text-xs text-gray-400">Translate to</p>
          {['English', 'Bulgarian', 'German', 'French', 'Spanish', 'Italian', 'Portuguese', 'Japanese', 'Chinese'].map(lang => (
            <button key={lang} onClick={() => translateMutation.mutate(lang)} className="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50">
              {lang}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
