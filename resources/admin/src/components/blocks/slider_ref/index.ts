import { blockRegistry } from '../registry';
import { sliderRefDefinition } from './definition';
import { SliderRefPreview } from './Preview';
import { SliderRefEditor } from './Editor';

blockRegistry.register(sliderRefDefinition, SliderRefPreview, SliderRefEditor);
