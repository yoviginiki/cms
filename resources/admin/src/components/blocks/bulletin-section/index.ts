import { blockRegistry } from '../registry';
import { bulletinSectionDefinition } from './definition';
import { BulletinSectionPreview } from './Preview';
import { BulletinSectionEditor } from './Editor';

blockRegistry.register(bulletinSectionDefinition, BulletinSectionPreview, BulletinSectionEditor);
