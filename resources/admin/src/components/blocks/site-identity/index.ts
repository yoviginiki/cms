import { blockRegistry } from '../registry';
import { siteIdentityDefinition } from './definition';
import { SiteIdentityPreview } from './Preview';
import { SiteIdentityEditor } from './Editor';

blockRegistry.register(siteIdentityDefinition, SiteIdentityPreview, SiteIdentityEditor);
