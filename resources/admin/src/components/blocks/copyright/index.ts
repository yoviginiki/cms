import { blockRegistry } from '../registry';
import { copyrightDefinition } from './definition';
import { CopyrightPreview } from './Preview';
import { CopyrightEditor } from './Editor';

blockRegistry.register(copyrightDefinition, CopyrightPreview, CopyrightEditor);
