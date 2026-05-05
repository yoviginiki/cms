import { blockRegistry } from '../registry';
import { relatedpostsDefinition } from './definition';
import { RelatedpostsPreview } from './Preview';
import { RelatedpostsEditor } from './Editor';

blockRegistry.register(relatedpostsDefinition, RelatedpostsPreview, RelatedpostsEditor);
