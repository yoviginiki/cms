import { blockRegistry } from '../registry';
import { beforeafterDefinition } from './definition';
import { BeforeafterPreview } from './Preview';
import { BeforeafterEditor } from './Editor';

blockRegistry.register(beforeafterDefinition, BeforeafterPreview, BeforeafterEditor);
