import { blockRegistry } from '../registry';
import { featuregridDefinition } from './definition';
import { FeaturegridPreview } from './Preview';
import { FeaturegridEditor } from './Editor';

blockRegistry.register(featuregridDefinition, FeaturegridPreview, FeaturegridEditor);
