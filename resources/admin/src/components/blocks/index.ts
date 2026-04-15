/**
 * Block Registry Entry Point
 *
 * Every block type must be imported here to register with the global blockRegistry.
 * To add a new block type:
 *   1. Create a folder: components/blocks/my-block/
 *   2. Add definition.ts, Preview.tsx, Editor.tsx, index.ts
 *   3. Add an import line below
 *
 * The index.ts in each block folder calls blockRegistry.register() on import.
 */

import './hero';
import './text';
import './image';
import './columns';
import './heading';

export { blockRegistry } from './registry';
