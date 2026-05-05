import { blockRegistry } from '../registry';
import { logostripDefinition } from './definition';
import { LogostripPreview } from './Preview';
import { LogostripEditor } from './Editor';

blockRegistry.register(logostripDefinition, LogostripPreview, LogostripEditor);
