import { blockRegistry } from '../registry';
import { resultsGridDefinition } from './definition';
import { ResultsGridPreview } from './Preview';
import { ResultsGridEditor } from './Editor';

blockRegistry.register(resultsGridDefinition, ResultsGridPreview, ResultsGridEditor);
