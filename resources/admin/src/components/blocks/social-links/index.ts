import { blockRegistry } from '../registry';
import { socialLinksDefinition } from './definition';
import { SocialLinksPreview } from './Preview';
import { SocialLinksEditor } from './Editor';

blockRegistry.register(socialLinksDefinition, SocialLinksPreview, SocialLinksEditor);
