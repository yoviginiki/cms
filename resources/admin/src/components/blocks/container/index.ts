import { blockRegistry } from '../registry';
import { containerDefinition } from './definition';
import { ContainerPreview } from './Preview';
import { ContainerEditor } from './Editor';

blockRegistry.register(containerDefinition, ContainerPreview, ContainerEditor);
