import { blockRegistry } from '../registry';
import { accordionDefinition } from './definition';
import { AccordionPreview } from './Preview';
import { AccordionEditor } from './Editor';

blockRegistry.register(accordionDefinition, AccordionPreview, AccordionEditor);
