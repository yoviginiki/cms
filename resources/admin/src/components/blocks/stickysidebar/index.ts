import { blockRegistry } from '../registry';
import { stickysidebarDefinition } from './definition';
import { StickySidebarPreview } from './Preview';
import { StickySidebarEditor } from './Editor';

blockRegistry.register(stickysidebarDefinition, StickySidebarPreview, StickySidebarEditor);
