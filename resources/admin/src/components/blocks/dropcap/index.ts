import { blockRegistry } from '../registry';
import { dropcapDefinition } from './definition';
import { DropcapPreview } from './Preview';
import { DropcapEditor } from './Editor';

blockRegistry.register(dropcapDefinition, DropcapPreview, DropcapEditor);
