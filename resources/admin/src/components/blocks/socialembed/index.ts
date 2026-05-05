import { blockRegistry } from '../registry';
import { socialembedDefinition } from './definition';
import { SocialembedPreview } from './Preview';
import { SocialembedEditor } from './Editor';

blockRegistry.register(socialembedDefinition, SocialembedPreview, SocialembedEditor);
