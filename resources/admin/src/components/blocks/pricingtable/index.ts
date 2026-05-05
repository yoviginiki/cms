import { blockRegistry } from '../registry';
import { pricingtableDefinition } from './definition';
import { PricingtablePreview } from './Preview';
import { PricingtableEditor } from './Editor';

blockRegistry.register(pricingtableDefinition, PricingtablePreview, PricingtableEditor);
