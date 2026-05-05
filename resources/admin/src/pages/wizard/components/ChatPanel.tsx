import { useRef, useEffect, useState, useCallback } from 'react';
import { Send, Loader2 } from 'lucide-react';
import { useWizardStore } from '../store';
import { STEP_LABELS } from '../types';
import ChatMessage from './ChatMessage';

export default function ChatPanel() {
  const { session, messages, isStreaming, streamingText, sendUserMessage } = useWizardStore();
  const [input, setInput] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  const step = session?.current_step ?? 1;
  const stepMessages = messages[step] || [];

  // Auto-scroll on new messages or streaming text
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [stepMessages.length, streamingText]);

  const handleSend = useCallback(() => {
    const text = input.trim();
    if (!text || isStreaming) return;
    setInput('');
    sendUserMessage(text);
  }, [input, isStreaming, sendUserMessage]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      handleSend();
    }
  };

  // Locked step summaries
  const lockedSummaries: { step: number; label: string; summary: string }[] = [];
  if (session) {
    if (session.step1_brief && step > 1) {
      lockedSummaries.push({ step: 1, label: 'Brief', summary: session.step1_brief.feeling || 'Defined' });
    }
    if (session.step2_structure && step > 2) {
      const count = session.step2_structure.articles?.length || 0;
      const pages = session.step2_structure.articles?.reduce((sum, a) => sum + a.pages, 0) || 0;
      lockedSummaries.push({ step: 2, label: 'Structure', summary: `${count} articles, ${pages} pages` });
    }
    if (session.step3_article_selection && step > 3) {
      lockedSummaries.push({ step: 3, label: 'Selected', summary: session.step3_article_selection.selected_slug });
    }
    if (session.step4_analyses?.length && step > 4) {
      lockedSummaries.push({ step: 4, label: 'Analyzed', summary: `${session.step4_analyses.length} article(s)` });
    }
    if (session.step5_directions?.length && step > 5) {
      lockedSummaries.push({ step: 5, label: 'Direction', summary: `${session.step5_directions.length} chosen` });
    }
    if (session.step6_thumbnails?.length && step > 6) {
      lockedSummaries.push({ step: 6, label: 'Thumbnails', summary: `${session.step6_thumbnails.length} planned` });
    }
  }

  return (
    <div className="flex flex-col h-full bg-base-100">
      {/* Header */}
      <div className="px-4 py-2 border-b border-base-300/20 shrink-0">
        <div className="text-[15px] font-medium text-base-content/50">
          Step {step}: {STEP_LABELS[step]}
        </div>
      </div>

      {/* Locked step summaries */}
      {lockedSummaries.length > 0 && (
        <div className="px-3 py-2 border-b border-base-300/15 flex gap-2 flex-wrap shrink-0">
          {lockedSummaries.map(s => (
            <div key={s.step} className="badge badge-sm badge-ghost text-[12px] gap-1">
              <span className="font-semibold">{s.label}:</span> {s.summary}
            </div>
          ))}
        </div>
      )}

      {/* Messages */}
      <div ref={scrollRef} className="flex-1 overflow-y-auto px-3 py-3">
        {stepMessages.length === 0 && !isStreaming && (
          <div className="text-center text-[14px] text-base-content/25 mt-8">
            Start the conversation for {STEP_LABELS[step]}
          </div>
        )}

        {stepMessages.map(msg => (
          <ChatMessage
            key={msg.id}
            role={msg.role}
            content={msg.content}
            createdAt={msg.created_at}
          />
        ))}

        {/* Streaming message */}
        {isStreaming && streamingText && (
          <ChatMessage
            role="assistant"
            content={streamingText}
            isStreaming
          />
        )}

        {isStreaming && !streamingText && (
          <div className="flex items-center gap-2 text-[15px] text-base-content/30 ml-8">
            <Loader2 size={12} className="animate-spin" /> Thinking...
          </div>
        )}
      </div>

      {/* Input */}
      <div className="p-3 border-t border-base-300/20 shrink-0">
        <div className="flex gap-2">
          <textarea
            ref={inputRef}
            value={input}
            onChange={e => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            disabled={isStreaming}
            placeholder={isStreaming ? 'Waiting for response...' : 'Type a message... (Ctrl+Enter to send)'}
            className="textarea textarea-bordered textarea-sm flex-1 min-h-[40px] max-h-[120px] text-[14px] resize-none"
            rows={2}
          />
          <button
            onClick={handleSend}
            disabled={isStreaming || !input.trim()}
            className="btn btn-primary btn-sm btn-square shrink-0 self-end"
          >
            {isStreaming ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />}
          </button>
        </div>
      </div>
    </div>
  );
}
