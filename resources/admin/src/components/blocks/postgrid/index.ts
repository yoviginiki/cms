import { blockRegistry } from '../registry';
import { postgridDefinition } from './definition';
import { PostgridPreview } from './Preview';
import { PostgridEditor } from './Editor';

blockRegistry.register(postgridDefinition, PostgridPreview, PostgridEditor);
