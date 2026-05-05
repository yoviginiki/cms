import { blockRegistry } from '../registry';
import { videoDefinition } from './definition';
import { VideoPreview } from './Preview';
import { VideoEditor } from './Editor';

blockRegistry.register(videoDefinition, VideoPreview, VideoEditor);
