import { blockRegistry } from '../registry';
import { groupDefinition } from './definition';
import { GroupPreview } from './Preview';
import { GroupEditor } from './Editor';

blockRegistry.register(groupDefinition, GroupPreview, GroupEditor);
