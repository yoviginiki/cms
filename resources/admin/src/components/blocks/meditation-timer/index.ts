import { blockRegistry } from '../registry';
import { meditationTimerDefinition } from './definition';
import { MeditationTimerPreview } from './Preview';
import { MeditationTimerEditor } from './Editor';

blockRegistry.register(meditationTimerDefinition, MeditationTimerPreview, MeditationTimerEditor);
