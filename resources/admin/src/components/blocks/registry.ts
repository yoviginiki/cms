import type { FC } from 'react';
import type { BlockCategory, BlockComponentProps, BlockDefinition, BlockEditorProps } from '@/types/blocks';

interface BlockRegistration {
  definition: BlockDefinition;
  Preview: FC<BlockComponentProps>;
  Editor: FC<BlockEditorProps>;
}

class BlockRegistry {
  private blocks = new Map<string, BlockRegistration>();

  register(
    definition: BlockDefinition,
    Preview: FC<BlockComponentProps>,
    Editor: FC<BlockEditorProps>,
  ): void {
    this.blocks.set(definition.type, { definition, Preview, Editor });
  }

  get(type: string): BlockRegistration | undefined {
    return this.blocks.get(type);
  }

  getAll(): Map<string, BlockRegistration> {
    return this.blocks;
  }

  getByCategory(category: BlockCategory): BlockDefinition[] {
    const result: BlockDefinition[] = [];
    for (const reg of this.blocks.values()) {
      if (reg.definition.category === category) {
        result.push(reg.definition);
      }
    }
    return result;
  }
}

export const blockRegistry = new BlockRegistry();
