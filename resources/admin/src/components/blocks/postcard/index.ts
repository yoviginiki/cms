import { blockRegistry } from '../registry';
import { postcardDefinition } from './definition';
import { PostcardPreview } from './Preview';
import { PostcardEditor } from './Editor';

blockRegistry.register(postcardDefinition, PostcardPreview, PostcardEditor);
