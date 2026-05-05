import { blockRegistry } from '../registry';
import { testimonialDefinition } from './definition';
import { TestimonialPreview } from './Preview';
import { TestimonialEditor } from './Editor';

blockRegistry.register(testimonialDefinition, TestimonialPreview, TestimonialEditor);
