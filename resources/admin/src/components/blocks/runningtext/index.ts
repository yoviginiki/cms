import { blockRegistry } from '../registry';
import { runningtextDefinition } from './definition';
import { RunningtextPreview } from './Preview';
import { RunningtextEditor } from './Editor';

blockRegistry.register(runningtextDefinition, RunningtextPreview, RunningtextEditor);
