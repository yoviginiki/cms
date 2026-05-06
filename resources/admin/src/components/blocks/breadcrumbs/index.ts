import { blockRegistry } from '../registry';
import { breadcrumbsDefinition } from './definition';
import { BreadcrumbsPreview } from './Preview';
import { BreadcrumbsEditor } from './Editor';

blockRegistry.register(breadcrumbsDefinition, BreadcrumbsPreview, BreadcrumbsEditor);
