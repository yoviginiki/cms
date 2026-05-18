import { blockRegistry } from '../registry';
import { postNavigationDefinition } from './definition';
import { PostNavigationPreview } from './Preview';
import { PostNavigationEditor } from './Editor';

blockRegistry.register(postNavigationDefinition, PostNavigationPreview, PostNavigationEditor);
