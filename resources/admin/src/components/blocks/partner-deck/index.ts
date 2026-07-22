import { blockRegistry } from '../registry';
import { partnerDeckDefinition } from './definition';
import { PartnerDeckPreview } from './Preview';
import { PartnerDeckEditor } from './Editor';

blockRegistry.register(partnerDeckDefinition, PartnerDeckPreview, PartnerDeckEditor);
