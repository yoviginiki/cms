import { blockRegistry } from '../registry';
import { sectionDefinition } from './definition';
import { SectionPreview } from './Preview';
import { SectionEditor } from './Editor';

blockRegistry.register(sectionDefinition, SectionPreview, SectionEditor);
