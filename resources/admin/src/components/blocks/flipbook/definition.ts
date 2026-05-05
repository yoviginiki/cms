import type { BlockDefinition } from '@/types/blocks';

export interface FlipbookBlockData {
  mode: 'realistic' | 'minimal';
  aspect_ratio: '1:1' | '2:3' | '3:4' | '210:297' | 'custom';
  custom_width_px: number | null;
  custom_height_px: number | null;
  flipping_time_ms: number;
  show_cover: boolean;
  max_shadow_opacity: number;
  click_to_flip: boolean;
  swipe_to_flip: boolean;
  start_page: number;
  show_nav_bar: boolean;
  show_fullscreen: boolean;
  show_page_indicator: boolean;
  // Content source
  source: 'children' | 'pdf' | 'category';
  pdf_asset_id: string | null;
  pdf_url: string;
  category_id: string | null;
  posts_order: 'date_desc' | 'date_asc' | 'title_asc' | 'title_desc';
  posts_limit: number;
}

export const flipbookDefinition: BlockDefinition = {
  type: 'flipbook',
  category: 'layout',
  label: 'Flipbook',
  icon: 'BookOpen',
  description: 'Interactive page-turning flipbook. Use a PDF, category articles, or child blocks as pages.',
  defaultData: {
    mode: 'realistic',
    aspect_ratio: '2:3',
    custom_width_px: null,
    custom_height_px: null,
    flipping_time_ms: 800,
    show_cover: true,
    max_shadow_opacity: 0.5,
    click_to_flip: true,
    swipe_to_flip: true,
    start_page: 0,
    show_nav_bar: true,
    show_fullscreen: true,
    show_page_indicator: true,
    source: 'pdf',
    pdf_asset_id: null,
    pdf_url: '',
    category_id: null,
    posts_order: 'date_desc',
    posts_limit: 50,
  } satisfies FlipbookBlockData,
  allowsChildren: true,
  maxChildren: 200,
  tier: 'core',
};
