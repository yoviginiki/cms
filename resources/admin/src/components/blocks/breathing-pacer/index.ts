import { blockRegistry } from '../registry';
import { breathingPacerDefinition } from './definition';
import { BreathingPacerPreview } from './Preview';
import { BreathingPacerEditor } from './Editor';

blockRegistry.register(breathingPacerDefinition, BreathingPacerPreview, BreathingPacerEditor);
