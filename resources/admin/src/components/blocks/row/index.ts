import { blockRegistry } from '@/components/blocks/registry';
import { rowDefinition } from './definition';
import { RowPreview } from './Preview';
import { RowEditor } from './Editor';

blockRegistry.register(rowDefinition, RowPreview, RowEditor);
