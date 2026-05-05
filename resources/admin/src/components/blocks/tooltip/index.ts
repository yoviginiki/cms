import { blockRegistry } from '../registry';
import { tooltipDefinition } from './definition';
import { TooltipPreview } from './Preview';
import { TooltipEditor } from './Editor';

blockRegistry.register(tooltipDefinition, TooltipPreview, TooltipEditor);
