import { blockRegistry } from '../registry';
import { pelvicTrainerDefinition } from './definition';
import { PelvicTrainerPreview } from './Preview';
import { PelvicTrainerEditor } from './Editor';

blockRegistry.register(pelvicTrainerDefinition, PelvicTrainerPreview, PelvicTrainerEditor);
