import { useState } from 'react';
import type { MagTypography } from '@/types/magazine';

interface MagTypographyPanelProps {
  value: MagTypography;
  onChange: (v: Partial<MagTypography>) => void;
}

const FONT_WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];
const ALIGN_OPTIONS: MagTypography['textAlign'][] = ['left', 'center', 'right', 'justify'];
const TRANSFORM_OPTIONS: MagTypography['textTransform'][] = ['none', 'uppercase', 'lowercase', 'capitalize', 'small-caps'];

export default function MagTypographyPanel({ value, onChange }: MagTypographyPanelProps) {
  const [advancedOpen, setAdvancedOpen] = useState(false);

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Typography</h3>

      {/* Font family */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Font family</label>
        <input
          type="text"
          value={value.fontFamily}
          onChange={(e) => onChange({ fontFamily: e.target.value })}
          className="input input-bordered input-xs w-full"
          placeholder="Inter, Georgia..."
        />
      </div>

      {/* Size & Weight */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Font size</label>
          <input
            type="number"
            min={6}
            max={200}
            value={value.fontSize}
            onChange={(e) => onChange({ fontSize: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Weight</label>
          <select
            value={value.fontWeight}
            onChange={(e) => onChange({ fontWeight: Number(e.target.value) })}
            className="select select-bordered select-xs w-full"
          >
            {FONT_WEIGHTS.map((w) => (
              <option key={w} value={w}>{w}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Italic toggle */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={value.fontStyle === 'italic'}
          onChange={(e) => onChange({ fontStyle: e.target.checked ? 'italic' : 'normal' })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Italic</span>
      </label>

      {/* Line height & Letter spacing */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Line height</label>
          <input
            type="number"
            min={0.5}
            max={4}
            step={0.1}
            value={value.lineHeight}
            onChange={(e) => onChange({ lineHeight: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Letter spacing (em)</label>
          <input
            type="number"
            min={-0.1}
            max={0.5}
            step={0.01}
            value={value.letterSpacing}
            onChange={(e) => onChange({ letterSpacing: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Text align */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text align</label>
        <div className="flex gap-1">
          {ALIGN_OPTIONS.map((align) => (
            <button
              key={align}
              type="button"
              onClick={() => onChange({ textAlign: align })}
              className={`btn btn-xs ${value.textAlign === align ? 'btn-primary' : 'btn-ghost'}`}
            >
              {align.charAt(0).toUpperCase() + align.slice(1)}
            </button>
          ))}
        </div>
      </div>

      {/* Text transform */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text transform</label>
        <select
          value={value.textTransform}
          onChange={(e) => onChange({ textTransform: e.target.value as MagTypography['textTransform'] })}
          className="select select-bordered select-xs w-full"
        >
          {TRANSFORM_OPTIONS.map((t) => (
            <option key={t} value={t}>{t}</option>
          ))}
        </select>
      </div>

      {/* Text color */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text color</label>
        <div className="flex gap-1">
          <input
            type="color"
            value={value.textColor}
            onChange={(e) => onChange({ textColor: e.target.value })}
            className="w-8 h-6 cursor-pointer rounded border border-base-300"
          />
          <input
            type="text"
            value={value.textColor}
            onChange={(e) => onChange({ textColor: e.target.value })}
            className="input input-bordered input-xs flex-1"
          />
        </div>
      </div>

      {/* Text indent */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text indent</label>
        <input
          type="number"
          value={value.textIndent}
          onChange={(e) => onChange({ textIndent: Number(e.target.value) })}
          className="input input-bordered input-xs w-full"
        />
      </div>

      {/* Paragraph spacing */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Space before</label>
          <input
            type="number"
            value={value.paragraphSpacingBefore}
            onChange={(e) => onChange({ paragraphSpacingBefore: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Space after</label>
          <input
            type="number"
            value={value.paragraphSpacingAfter}
            onChange={(e) => onChange({ paragraphSpacingAfter: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Advanced section */}
      <div className="border-t border-base-300 pt-2">
        <button
          type="button"
          onClick={() => setAdvancedOpen(!advancedOpen)}
          className="text-[10px] text-base-content/40 hover:text-base-content/60 w-full text-left"
        >
          {advancedOpen ? '- Advanced' : '+ Advanced'}
        </button>

        {advancedOpen && (
          <div className="space-y-3 mt-2">
            {/* Hyphenation & Hanging punctuation */}
            <label className="flex items-center gap-1.5 cursor-pointer">
              <input
                type="checkbox"
                checked={value.hyphenation}
                onChange={(e) => onChange({ hyphenation: e.target.checked })}
                className="checkbox checkbox-xs"
              />
              <span className="text-[10px] text-base-content/40">Hyphenation</span>
            </label>

            <label className="flex items-center gap-1.5 cursor-pointer">
              <input
                type="checkbox"
                checked={value.hangingPunctuation}
                onChange={(e) => onChange({ hangingPunctuation: e.target.checked })}
                className="checkbox checkbox-xs"
              />
              <span className="text-[10px] text-base-content/40">Hanging punctuation</span>
            </label>

            {/* Max chars per line */}
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Max chars per line (0=off)</label>
              <input
                type="number"
                min={0}
                max={75}
                value={value.maxCharsPerLine ?? 0}
                onChange={(e) => {
                  const v = Number(e.target.value);
                  onChange({ maxCharsPerLine: v === 0 ? null : Math.max(45, Math.min(75, v)) });
                }}
                className="input input-bordered input-xs w-full"
              />
            </div>

            {/* Drop cap */}
            <div className="space-y-1">
              <h4 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Drop cap</h4>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={value.dropCap.enabled}
                  onChange={(e) => onChange({ dropCap: { ...value.dropCap, enabled: e.target.checked } })}
                  className="checkbox checkbox-xs"
                />
                <span className="text-[10px] text-base-content/40">Enable</span>
              </label>
              {value.dropCap.enabled && (
                <div className="space-y-2 pl-4">
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Lines</label>
                    <input
                      type="number"
                      min={2}
                      max={6}
                      value={value.dropCap.lines}
                      onChange={(e) => onChange({ dropCap: { ...value.dropCap, lines: Number(e.target.value) } })}
                      className="input input-bordered input-xs w-full"
                    />
                  </div>
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Font override</label>
                    <input
                      type="text"
                      value={value.dropCap.font ?? ''}
                      onChange={(e) => onChange({ dropCap: { ...value.dropCap, font: e.target.value || null } })}
                      className="input input-bordered input-xs w-full"
                    />
                  </div>
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
                    <input
                      type="text"
                      value={value.dropCap.color ?? ''}
                      onChange={(e) => onChange({ dropCap: { ...value.dropCap, color: e.target.value || null } })}
                      className="input input-bordered input-xs w-full"
                    />
                  </div>
                </div>
              )}
            </div>

            {/* OpenType */}
            <div className="space-y-1">
              <h4 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">OpenType</h4>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={value.openType.ligatures}
                  onChange={(e) => onChange({ openType: { ...value.openType, ligatures: e.target.checked } })}
                  className="checkbox checkbox-xs"
                />
                <span className="text-[10px] text-base-content/40">Ligatures</span>
              </label>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={value.openType.oldstyleNums}
                  onChange={(e) => onChange({ openType: { ...value.openType, oldstyleNums: e.target.checked } })}
                  className="checkbox checkbox-xs"
                />
                <span className="text-[10px] text-base-content/40">Oldstyle nums</span>
              </label>
              <label className="flex items-center gap-1.5 cursor-pointer">
                <input
                  type="checkbox"
                  checked={value.openType.smallCaps}
                  onChange={(e) => onChange({ openType: { ...value.openType, smallCaps: e.target.checked } })}
                  className="checkbox checkbox-xs"
                />
                <span className="text-[10px] text-base-content/40">Small caps</span>
              </label>
            </div>

            {/* Orphans & Widows */}
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="text-[10px] text-base-content/40 mb-0.5 block">Orphans</label>
                <input
                  type="number"
                  min={1}
                  max={5}
                  value={value.orphans}
                  onChange={(e) => onChange({ orphans: Number(e.target.value) })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label className="text-[10px] text-base-content/40 mb-0.5 block">Widows</label>
                <input
                  type="number"
                  min={1}
                  max={5}
                  value={value.widows}
                  onChange={(e) => onChange({ widows: Number(e.target.value) })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Paragraph style */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Paragraph style</label>
        <select
          value={value.paragraphStyleId ?? ''}
          onChange={(e) => onChange({ paragraphStyleId: e.target.value || null })}
          className="select select-bordered select-xs w-full"
        >
          <option value="">None</option>
        </select>
      </div>

      {/* Character style */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Character style</label>
        <select
          value={value.characterStyleId ?? ''}
          onChange={(e) => onChange({ characterStyleId: e.target.value || null })}
          className="select select-bordered select-xs w-full"
        >
          <option value="">None</option>
        </select>
      </div>
    </div>
  );
}
