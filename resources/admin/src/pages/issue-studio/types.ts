export type SessionStatus = 'interviewing' | 'flatplanning' | 'generating' | 'complete' | 'abandoned';

export type Genre = 'politics' | 'art-culture' | 'business' | 'lifestyle' | 'interview';

export interface Material {
  id: string;
  kind: 'text' | 'image' | 'interview';
  title: string;
  content?: string;
  word_count?: number;
  asset_id?: string;
  added_at: string;
}

export interface Brief {
  topic: string | null;
  working_title: string | null;
  audience: string | null;
  tone: string | null;
  genre: Genre | null;
  page_ambition: number | null;
  notes: string[];
  materials: Material[];
}

export interface TranscriptEntry {
  role: 'user' | 'assistant';
  text: string;
  at: string;
}

export interface TokenUsageEntry {
  phase: string;
  model: string;
  input: number;
  output: number;
  cache_write: number;
  cache_read: number;
  at: string;
}

export interface StudioSession {
  id: string;
  site_id: string;
  title: string | null;
  status: SessionStatus;
  brief: Brief;
  transcript: TranscriptEntry[];
  flatplan: unknown | null;
  magazine_issue_id: string | null;
  token_usage: TokenUsageEntry[];
  total_tokens: number;
  created_at: string;
  updated_at: string;
}

export interface SessionSummary {
  id: string;
  site_id: string;
  title: string | null;
  status: SessionStatus;
  topic: string | null;
  material_count: number;
  total_tokens: number;
  updated_at: string;
}

export const STATUS_LABELS: Record<SessionStatus, string> = {
  interviewing: 'Interview',
  flatplanning: 'Flatplan',
  generating: 'Generating',
  complete: 'Complete',
  abandoned: 'Abandoned',
};

export const GENRE_LABELS: Record<Genre, string> = {
  politics: 'Politics',
  'art-culture': 'Art & Culture',
  business: 'Business',
  lifestyle: 'Lifestyle',
  interview: 'Interview',
};
