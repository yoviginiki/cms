import { blockRegistry } from '../registry';
import { chartDefinition } from './definition';
import { ChartPreview } from './Preview';
import { ChartEditor } from './Editor';

blockRegistry.register(chartDefinition, ChartPreview, ChartEditor);
