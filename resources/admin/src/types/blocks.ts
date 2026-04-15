export interface BlockData {
  id: string;
  type: string;
  data: Record<string, unknown>;
  children: BlockData[];
  order: number;
}

export interface BlockDefinition {
  type: string;
  category: BlockCategory;
  label: string;
  icon: string;
  defaultData: Record<string, unknown>;
  allowsChildren: boolean;
}

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
  | 'forms';
