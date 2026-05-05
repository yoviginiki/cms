import { blockRegistry } from '../registry';
import { readingprogressDefinition } from './definition';
import { ReadingprogressPreview } from './Preview';
import { ReadingprogressEditor } from './Editor';

blockRegistry.register(readingprogressDefinition, ReadingprogressPreview, ReadingprogressEditor);
