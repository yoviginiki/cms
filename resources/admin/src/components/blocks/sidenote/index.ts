import { blockRegistry } from '../registry';
import { sidenoteDefinition } from './definition';
import { SidenotePreview } from './Preview';
import { SidenoteEditor } from './Editor';

blockRegistry.register(sidenoteDefinition, SidenotePreview, SidenoteEditor);
