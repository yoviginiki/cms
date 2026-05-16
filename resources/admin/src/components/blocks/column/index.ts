import { blockRegistry } from '@/components/blocks/registry';
import { columnDefinition } from './definition';
import { ColumnPreview } from './Preview';
import { ColumnEditor } from './Editor';

blockRegistry.register(columnDefinition, ColumnPreview, ColumnEditor);
