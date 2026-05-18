import { blockRegistry } from '../registry';
import { postVideoDefinition } from './definition';
import { PostVideoPreview } from './Preview';
import { PostVideoEditor } from './Editor';

blockRegistry.register(postVideoDefinition, PostVideoPreview, PostVideoEditor);
