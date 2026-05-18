import { blockRegistry } from '../registry';
import { postMetaDefinition } from './definition';
import { PostMetaPreview } from './Preview';
import { PostMetaEditor } from './Editor';

blockRegistry.register(postMetaDefinition, PostMetaPreview, PostMetaEditor);
