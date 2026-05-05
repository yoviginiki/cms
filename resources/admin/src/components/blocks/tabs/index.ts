import { blockRegistry } from '../registry';
import { tabsDefinition } from './definition';
import { TabsPreview } from './Preview';
import { TabsEditor } from './Editor';

blockRegistry.register(tabsDefinition, TabsPreview, TabsEditor);
