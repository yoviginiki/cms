import { blockRegistry } from '../registry';
import { headingDefinition } from './definition';
import { HeadingPreview } from './Preview';
import { HeadingEditor } from './Editor';

blockRegistry.register(headingDefinition, HeadingPreview, HeadingEditor);
