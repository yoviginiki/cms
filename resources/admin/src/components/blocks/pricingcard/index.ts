import { blockRegistry } from '../registry';
import { pricingcardDefinition } from './definition';
import { PricingcardPreview } from './Preview';
import { PricingcardEditor } from './Editor';

blockRegistry.register(pricingcardDefinition, PricingcardPreview, PricingcardEditor);
