import { blockRegistry } from '../registry';
import { htmlEmbedDefinition } from './definition';
import { HtmlEmbedPreview } from './Preview';
import { HtmlEmbedEditor } from './Editor';

blockRegistry.register(htmlEmbedDefinition, HtmlEmbedPreview, HtmlEmbedEditor);
