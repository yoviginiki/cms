import { blockRegistry } from '../registry';
import { langswitcherDefinition } from './definition';
import { LangSwitcherPreview } from './Preview';
import { LangSwitcherEditor } from './Editor';

blockRegistry.register(langswitcherDefinition, LangSwitcherPreview, LangSwitcherEditor);
