import type { BlockEditorProps } from '@/types/blocks';

export const PostContentEditor: React.FC<BlockEditorProps> = () => (
  <div className="p-3">
    <div className="bg-indigo-50 text-indigo-700 text-xs p-3 rounded">
      <p className="font-medium mb-1">Dynamic Post Content</p>
      <p>This block renders the full post content (all blocks from the post editor). No configuration needed.</p>
    </div>
  </div>
);
