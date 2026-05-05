import { blockRegistry } from '../registry';
import { paywallDefinition } from './definition';
import { PaywallPreview } from './Preview';
import { PaywallEditor } from './Editor';

blockRegistry.register(paywallDefinition, PaywallPreview, PaywallEditor);
