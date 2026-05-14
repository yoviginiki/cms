// ─── Typography ───
export interface TypographyProps {
  fontFamily?: string;
  fontSize?: string;
  fontWeight?: 400 | 500;
  lineHeight?: string;
  letterSpacing?: string;
  textAlign?: 'left' | 'center' | 'right' | 'justify';
  textTransform?: 'none' | 'uppercase' | 'lowercase' | 'capitalize';
  textColor?: string;
  maxCharactersPerLine?: number;
  paragraphSpacingAfter?: string;
}

// ─── Spacing ───
export interface SpacingProps {
  marginTop?: string;
  marginRight?: string;
  marginBottom?: string;
  marginLeft?: string;
  paddingTop?: string;
  paddingRight?: string;
  paddingBottom?: string;
  paddingLeft?: string;
  gap?: string;
}

// ─── Visual ───
export interface VisualProps {
  backgroundColor?: string;
  backgroundImage?: string;
  backgroundGradient?: string;
  borderWidth?: string;
  borderColor?: string;
  borderStyle?: 'none' | 'solid' | 'dashed' | 'dotted';
  borderRadius?: string | { topLeft?: string; topRight?: string; bottomRight?: string; bottomLeft?: string };
  boxShadow?: 'none' | 'sm' | 'md' | 'lg' | string;
  shadowMode?: 'preset' | 'custom';
  shadowCustom?: { x?: string; y?: string; blur?: string; spread?: string; color?: string; opacity?: number; inset?: boolean };
  opacity?: number;
  overflow?: 'visible' | 'hidden' | 'scroll';
}

// ─── Layout ───
export interface LayoutProps {
  width?: string;
  maxWidth?: string;
  minHeight?: string;
  alignment?: 'left' | 'center' | 'right' | 'stretch';
  display?: 'block' | 'flex' | 'grid' | 'none';
  flexDirection?: 'row' | 'column';
  justifyContent?: string;
  alignItems?: string;
  zIndex?: number;
  // Magazine editor freeform positioning (px on canvas)
  position?: 'static' | 'absolute';
  x?: number;
  y?: number;
  rotation?: number;
  locked?: boolean;
}

// ─── Combined style ───
export interface BlockStyleProps {
  typography?: TypographyProps;
  spacing?: SpacingProps;
  visual?: VisualProps;
  layout?: LayoutProps;
}

// ─── Responsive ───
export interface ResponsiveOverrides {
  tablet?: Partial<BlockStyleProps>;
  mobile?: Partial<BlockStyleProps>;
  hideOn?: ('desktop' | 'tablet' | 'mobile')[];
}

// ─── Animation ───
export interface AnimationProps {
  entrance?: 'none' | 'fade' | 'slide-up' | 'slide-down' | 'slide-left' | 'slide-right' | 'zoom' | 'scale-in';
  duration?: number;
  delay?: number;
  easing?: 'linear' | 'ease' | 'ease-in' | 'ease-out' | 'ease-in-out';
  trigger?: 'on-load' | 'on-scroll';
  hoverEffect?: 'none' | 'opacity' | 'lift' | 'glow';
}

// ─── Advanced ───
export interface AdvancedProps {
  customClass?: string;
  customCss?: string;
  htmlId?: string;
  ariaLabel?: string;
}

// ─── Block data — every block has this ───
export interface BlockData {
  id: string;
  type: string;
  data: Record<string, unknown>;
  children: BlockData[];
  order: number;
  style?: BlockStyleProps;
  responsive?: ResponsiveOverrides;
  animation?: AnimationProps;
  advanced?: AdvancedProps;
}

// ─── Block definition — what a block type IS ───
export interface BlockDefinition {
  type: string;
  category: BlockCategory;
  label: string;
  icon: string;
  description?: string;
  defaultData: Record<string, unknown>;
  allowsChildren: boolean;
  maxChildren?: number;
  hasTypography?: boolean;
  tier?: 'core' | 'advanced' | 'pro';
}

// ─── Component props ───
export interface BlockComponentProps {
  block: BlockData;
  isSelected: boolean;
  onUpdate: (data: Record<string, unknown>) => void;
  onSelect: () => void;
}

export interface BlockEditorProps extends BlockComponentProps {}

export type BlockCategory =
  | 'layout'
  | 'content'
  | 'media'
  | 'interactive'
  | 'commerce'
  | 'forms'
  | 'typography'
  | 'data'
  | 'blog'
  | 'embed'
  | 'navigation'
  | 'marketing'
  | 'advanced';
