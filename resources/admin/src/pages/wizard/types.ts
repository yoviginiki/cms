// ─── Step artifact schemas ───

export interface Step1Brief {
  feeling: string;
  reader_state: string;
  anchors: string[];
  page_count: number;
}

export interface StructureArticle {
  slug: string;
  title: string;
  pages: number;
  rhythm: 'dense' | 'medium' | 'breath';
  role: string;
  justification: string;
}

export interface Step2Structure {
  articles: StructureArticle[];
}

export interface Step3Selection {
  selected_slug: string;
}

export interface AnalysisBeat {
  name: string;
  description: string;
}

export interface SpreadAssignment {
  spread: number;
  beat: string;
  role: string;
  density: string;
  tension: string;
}

export interface Step4Analysis {
  article_slug: string;
  voice: { tone: string; register: string; posture: string };
  beats: AnalysisBeat[];
  spread_assignments: SpreadAssignment[];
}

export interface DirectionProposal {
  name: string;
  thesis: string;
  references: string[];
  typography: {
    display: string;
    text: string;
    scale_ratio: string;
    weight_palette: string;
    tracking_leading: string;
    signature_move: string;
  };
  grid: {
    columns: string;
    baseline: string;
    breaks: string;
    break_meaning: string;
  };
  image_strategy: {
    treatment: string;
    ratio: string;
    cross_spread: string;
  };
  rules: string[];
  banned_moves: string[];
  spread_relationship: string;
}

export interface Step5Directions {
  article_slug: string;
  proposed: DirectionProposal[];
  chosen: DirectionProposal | null;
}

export interface ThumbnailSpread {
  spread: number;
  weight_position: string;
  zones: { kind: 'text' | 'image'; rough: string }[];
  entry_exit: string;
  flagged_for_revision: boolean;
}

export interface Step6Thumbnails {
  article_slug: string;
  spreads: ThumbnailSpread[];
}

export interface Step7Review {
  review_complete: boolean;
  notes: string;
}

// ─── Discriminated artifact union ───

export type StepArtifact =
  | { step: 1; data: Step1Brief }
  | { step: 2; data: Step2Structure }
  | { step: 3; data: Step3Selection }
  | { step: 4; data: Step4Analysis }
  | { step: 5; data: Step5Directions }
  | { step: 6; data: Step6Thumbnails }
  | { step: 7; data: Step7Review };

// ─── Session and message types ───

export interface WizardMessage {
  id: string;
  step: number;
  role: 'user' | 'assistant';
  content: string;
  artifact_update: Record<string, unknown> | null;
  tokens_in: number | null;
  tokens_out: number | null;
  created_at: string;
}

export interface WizardSession {
  id: string;
  title: string | null;
  current_step: number;
  status: 'active' | 'provisioned' | 'abandoned';
  provisioned_issue_id: string | null;
  step1_brief: Step1Brief | null;
  step2_structure: Step2Structure | null;
  step3_article_selection: Step3Selection | null;
  step4_analyses: Step4Analysis[];
  step5_directions: Step5Directions[];
  step6_thumbnails: Step6Thumbnails[];
  messages?: WizardMessage[];
  created_at: string;
  updated_at: string;
}

// ─── SSE stream events ───

export type StreamEvent =
  | { type: 'delta'; text: string }
  | { type: 'complete'; message_id: string; artifact: Record<string, unknown> | null; tokens_in: number; tokens_out: number }
  | { type: 'error'; message: string };

// ─── Step metadata ───

export const STEP_LABELS = [
  '', // 0 placeholder
  'Brief',
  'Structure',
  'Select',
  'Analyze',
  'Directions',
  'Thumbnails',
  'Review',
] as const;

export const STEP_COUNT = 7;
