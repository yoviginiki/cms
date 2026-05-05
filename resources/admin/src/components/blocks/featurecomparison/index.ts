import { blockRegistry } from '../registry';
import { featurecomparisonDefinition } from './definition';
import { FeaturecomparisonPreview } from './Preview';
import { FeaturecomparisonEditor } from './Editor';

blockRegistry.register(featurecomparisonDefinition, FeaturecomparisonPreview, FeaturecomparisonEditor);
