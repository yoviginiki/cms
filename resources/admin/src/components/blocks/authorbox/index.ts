import { blockRegistry } from '../registry';
import { authorboxDefinition } from './definition';
import { AuthorboxPreview } from './Preview';
import { AuthorboxEditor } from './Editor';

blockRegistry.register(authorboxDefinition, AuthorboxPreview, AuthorboxEditor);
