import { blockRegistry } from '../registry';
import { audioDefinition } from './definition';
import { AudioPreview } from './Preview';
import { AudioEditor } from './Editor';

blockRegistry.register(audioDefinition, AudioPreview, AudioEditor);
