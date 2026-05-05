import { blockRegistry } from '../registry';
import { captionDefinition } from './definition';
import { CaptionPreview } from './Preview';
import { CaptionEditor } from './Editor';

blockRegistry.register(captionDefinition, CaptionPreview, CaptionEditor);
