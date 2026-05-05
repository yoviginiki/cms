import { blockRegistry } from '../registry';
import { modalDefinition } from './definition';
import { ModalPreview } from './Preview';
import { ModalEditor } from './Editor';

blockRegistry.register(modalDefinition, ModalPreview, ModalEditor);
