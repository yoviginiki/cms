import { blockRegistry } from '../registry';
import { imagecaptionDefinition } from './definition';
import { ImagecaptionPreview } from './Preview';
import { ImagecaptionEditor } from './Editor';

blockRegistry.register(imagecaptionDefinition, ImagecaptionPreview, ImagecaptionEditor);
