import { blockRegistry } from '../registry';
import { latestpostsDefinition } from './definition';
import { LatestpostsPreview } from './Preview';
import { LatestpostsEditor } from './Editor';

blockRegistry.register(latestpostsDefinition, LatestpostsPreview, LatestpostsEditor);
