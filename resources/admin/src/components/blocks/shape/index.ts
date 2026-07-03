import { blockRegistry } from '../registry';
import { shapeDefinition } from './definition';
import { ShapePreview } from './Preview';
import { ShapeEditor } from './Editor';

blockRegistry.register(shapeDefinition, ShapePreview, ShapeEditor);
