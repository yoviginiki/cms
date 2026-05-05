import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TextdividerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { style, customSymbol, width } = block.data as {
    style: string;
    customSymbol: string;
    width: string;
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Style</label>
        <select
          value={style || 'line'}
          onChange={(e) => onUpdate({ ...block.data, style: e.target.value })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="line">Line</option>
          <option value="dots">Dots</option>
          <option value="asterisks">Asterisks (* * *)</option>
          <option value="dinkus">Dinkus (***)</option>
          <option value="custom">Custom Symbol</option>
        </select>
      </div>
      {style === 'custom' && (
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Custom Symbol</label>
          <input
            type="text"
            value={customSymbol || ''}
            onChange={(e) => onUpdate({ ...block.data, customSymbol: e.target.value })}
            className="input input-bordered input-sm w-full text-[12px]"
            placeholder="Enter symbol(s)"
          />
        </div>
      )}
      <div>
        <label className="text-[11px] text-base-content/50 mb-1 block">Width</label>
        <select
          value={width || 'half'}
          onChange={(e) => onUpdate({ ...block.data, width: e.target.value })}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          <option value="full">Full</option>
          <option value="half">Half</option>
          <option value="quarter">Quarter</option>
        </select>
      </div>
    </div>
  );
};
