import { blockRegistry } from '../registry';
import { linkDefinition } from './definition';
import { LinkPreview } from './Preview';
import { LinkEditor } from './Editor';

blockRegistry.register(linkDefinition, LinkPreview, LinkEditor);
