import { blockRegistry } from '../registry';
import { linearGalleryDefinition } from './definition';
import { LinearGalleryPreview } from './Preview';
import { LinearGalleryEditor } from './Editor';

blockRegistry.register(linearGalleryDefinition, LinearGalleryPreview, LinearGalleryEditor);
