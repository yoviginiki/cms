import { blockRegistry } from '../registry';
import { sharebuttonsDefinition } from './definition';
import { SharebuttonsPreview } from './Preview';
import { SharebuttonsEditor } from './Editor';

blockRegistry.register(sharebuttonsDefinition, SharebuttonsPreview, SharebuttonsEditor);
