import { User, Bot } from 'lucide-react';

interface Props {
  role: 'user' | 'assistant';
  content: string;
  createdAt?: string;
  isStreaming?: boolean;
}

// Strip <artifact_update>...</artifact_update> from visible text
function stripArtifact(text: string): string {
  return text.replace(/<artifact_update>[\s\S]*?<\/artifact_update>/g, '').trim();
}

// Simple markdown-ish rendering (bold, italic, headers, lists, code)
function renderMarkdown(text: string): string {
  let html = text
    // Escape HTML
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    // Headers
    .replace(/^### (.+)$/gm, '<h4 class="font-medium text-[15px] mt-3 mb-1">$1</h4>')
    .replace(/^## (.+)$/gm, '<h3 class="font-semibold text-[15px] mt-3 mb-1">$1</h3>')
    .replace(/^# (.+)$/gm, '<h2 class="font-semibold text-sm mt-3 mb-1">$1</h2>')
    // Bold + italic
    .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    // Inline code
    .replace(/`([^`]+)`/g, '<code class="bg-base-300/30 px-1 rounded text-[15px]">$1</code>')
    // Lists
    .replace(/^- (.+)$/gm, '<li class="ml-3">$1</li>')
    .replace(/^\d+\. (.+)$/gm, '<li class="ml-3 list-decimal">$1</li>')
    // Line breaks (double newline = paragraph)
    .replace(/\n\n/g, '</p><p class="mt-2">')
    .replace(/\n/g, '<br/>');

  return `<p>${html}</p>`;
}

export default function ChatMessage({ role, content, createdAt, isStreaming }: Props) {
  const clean = stripArtifact(content);
  if (!clean && !isStreaming) return null;

  const isUser = role === 'user';

  return (
    <div className={`flex gap-2 ${isUser ? 'flex-row-reverse' : ''} mb-3`}>
      <div className={`w-6 h-6 rounded-full flex items-center justify-center shrink-0 mt-0.5 ${
        isUser ? 'bg-primary/15 text-primary' : 'bg-base-300/30 text-base-content/40'
      }`}>
        {isUser ? <User size={12} /> : <Bot size={12} />}
      </div>

      <div
        className={`max-w-[85%] rounded-xl px-3 py-2 text-[14px] leading-relaxed ${
          isUser
            ? 'bg-primary text-primary-content rounded-tr-sm'
            : 'bg-base-200/60 text-base-content/80 rounded-tl-sm'
        }`}
        title={createdAt ? new Date(createdAt).toLocaleString() : undefined}
      >
        {isUser ? (
          <p>{clean}</p>
        ) : (
          <div
            className="prose-sm [&_strong]:font-semibold [&_em]:italic [&_h2]:text-sm [&_h3]:text-[15px] [&_li]:text-[14px]"
            dangerouslySetInnerHTML={{ __html: renderMarkdown(clean) }}
          />
        )}
        {isStreaming && (
          <span className="inline-block w-1.5 h-3.5 bg-primary/60 animate-pulse ml-0.5" />
        )}
      </div>
    </div>
  );
}
