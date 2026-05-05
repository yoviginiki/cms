import { blockRegistry } from '../registry';
import { flipbookDefinition } from './definition';
import { FlipbookPreview } from './Preview';
import { FlipbookEditor } from './Editor';

blockRegistry.register(flipbookDefinition, FlipbookPreview, FlipbookEditor);
