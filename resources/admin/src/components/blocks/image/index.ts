import { blockRegistry } from '../registry';
import { imageDefinition } from './definition';
import { ImagePreview } from './Preview';
import { ImageEditor } from './Editor';

blockRegistry.register(imageDefinition, ImagePreview, ImageEditor);
