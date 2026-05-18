import { blockRegistry } from '../registry';
import { archivePaginationDefinition } from './definition';
import { ArchivePaginationPreview } from './Preview';
import { ArchivePaginationEditor } from './Editor';

blockRegistry.register(archivePaginationDefinition, ArchivePaginationPreview, ArchivePaginationEditor);
