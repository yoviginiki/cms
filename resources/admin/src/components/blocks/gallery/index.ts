import { blockRegistry } from '../registry';
import { galleryDefinition } from './definition';
import { GalleryPreview } from './Preview';
import { GalleryEditor } from './Editor';

blockRegistry.register(galleryDefinition, GalleryPreview, GalleryEditor);
