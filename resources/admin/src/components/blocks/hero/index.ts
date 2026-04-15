import { blockRegistry } from '../registry';
import { heroDefinition } from './definition';
import { HeroPreview } from './Preview';
import { HeroEditor } from './Editor';

blockRegistry.register(heroDefinition, HeroPreview, HeroEditor);
