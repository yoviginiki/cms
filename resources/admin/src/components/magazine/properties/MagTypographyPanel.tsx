import { useState } from 'react';
import type { MagTypography } from '@/types/magazine';

interface MagTypographyPanelProps {
  value: MagTypography;
  onChange: (v: Partial<MagTypography>) => void;
}

const FONT_WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];
const ALIGN_OPTIONS: MagTypography['textAlign'][] = ['left', 'center', 'right', 'justify'];
const TRANSFORM_OPTIONS: MagTypography['textTransform'][] = ['none', 'uppercase', 'lowercase', 'capitalize', 'small-caps'];

const CURATED_FONTS = [
  'Inter', 'Roboto', 'Open Sans', 'Montserrat', 'Lato', 'Poppins',
  'Merriweather', 'Playfair Display', 'Source Sans 3', 'Barlow',
  'Barlow Condensed', 'Manrope', 'Nunito Sans', 'Raleway', 'Oswald',
  'Georgia', 'Times New Roman', 'Arial', 'Helvetica',
];

interface ParagraphPreset {
  id: string;
  label: string;
  typography: Partial<MagTypography>;
}

const PARAGRAPH_PRESETS: ParagraphPreset[] = [
  { id: 'headline', label: 'Headline', typography: { fontFamily: 'Playfair Display', fontSize: 48, fontWeight: 700, lineHeight: 1.1, letterSpacing: -0.02, textColor: '#1a1a1a' } },
  { id: 'subheading', label: 'Subheading', typography: { fontFamily: 'Inter', fontSize: 24, fontWeight: 600, lineHeight: 1.3, letterSpacing: -0.01, textColor: '#333333' } },
  { id: 'body', label: 'Body', typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.6, letterSpacing: 0, textColor: '#1a1a1a' } },
  { id: 'caption', label: 'Caption', typography: { fontFamily: 'Inter', fontSize: 11, fontWeight: 400, lineHeight: 1.4, letterSpacing: 0.01, textColor: '#666666', fontStyle: 'italic' as const } },
  { id: 'quote', label: 'Quote', typography: { fontFamily: 'Merriweather', fontSize: 20, fontWeight: 400, lineHeight: 1.5, letterSpacing: 0, textColor: '#333333', fontStyle: 'italic' as const } },
];

export default function MagTypographyPanel({ value, onChange }: MagTypographyPanelProps) {
  const [advancedOpen, setAdvancedOpen] = useState(false);

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Typography</h3>

      {/* Paragraph style preset */}
      <div>
        <label htmlFor="magtypographypanel-style-preset-1" className="text-[10px] text-base-content/40 mb-0.5 block">Style preset</label>
        <select id="magtypographypanel-style-preset-1"
          value=""
          onChange={(e) => {
            const preset = PARAGRAPH_PRESETS.find(p => p.id === e.target.value);
            if (preset) onChange({ ...preset.typography, paragraphStyleId: preset.id });
          }}
          className="select select-bordered select-xs w-full"
        >
          <option value="" disabled>Apply preset...</option>
          {PARAGRAPH_PRESETS.map(p => (
            <option key={p.id} value={p.id}>{p.label}</option>
          ))}
        </select>
        {value.paragraphStyleId && (
          <p className="text-[9px] text-primary/60 mt-0.5">Based on: {PARAGRAPH_PRESETS.find(p => p.id === value.paragraphStyleId)?.label || value.paragraphStyleId}</p>
        )}
      </div>

      {/* Font family */}
      <div>
        <label htmlFor="magtypographypanel-font-family-2" className="text-[10px] text-base-content/40 mb-0.5 block">Font family</label>
        <select id="magtypographypanel-font-family-2"
          value={CURATED_FONTS.includes(value.fontFamily) ? value.fontFamily : '__custom'}
          onChange={(e) => {
            if (e.target.value !== '__custom') onChange({ fontFamily: e.target.value });
          }}
          className="select select-bordered select-xs w-full"
        >
          {CURATED_FONTS.map(f => (
            <option key={f} value={f} style={{ fontFamily: f }}>{f}</option>
          ))}
          {!CURATED_FONTS.includes(value.fontFamily) && (
            <option value="__custom">{value.fontFamily} (custom)</option>
          )}
        </select>
      </div>

      {/* Size & Weight */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="magtypographypanel-font-size-3" className="text-[10px] text-base-content/40 mb-0.5 block">Font size</label>
          <input id="magtypographypanel-font-size-3"
            type="number"
            min={6}
            max={200}
            value={value.fontSize}
            onChange={(e) => onChange({ fontSize: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="magtypographypanel-weight-4" className="text-[10px] text-base-content/40 mb-0.5 block">Weight</label>
          <select id="magtypographypanel-weight-4"
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
          <label htmlFor="magtypographypanel-line-height-5" className="text-[10px] text-base-content/40 mb-0.5 block">Line height</label>
          <input id="magtypographypanel-line-height-5"
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
          <label htmlFor="magtypographypanel-letter-spacing-em-6" className="text-[10px] text-base-content/40 mb-0.5 block">Letter spacing (em)</label>
          <input id="magtypographypanel-letter-spacing-em-6"
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
        <label htmlFor="magtypographypanel-text-transform-7" className="text-[10px] text-base-content/40 mb-0.5 block">Text transform</label>
        <select id="magtypographypanel-text-transform-7"
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
        <label htmlFor="magtypographypanel-text-indent-8" className="text-[10px] text-base-content/40 mb-0.5 block">Text indent</label>
        <input id="magtypographypanel-text-indent-8"
          type="number"
          value={value.textIndent}
          onChange={(e) => onChange({ textIndent: Number(e.target.value) })}
          className="input input-bordered input-xs w-full"
        />
      </div>

      {/* Paragraph spacing */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="magtypographypanel-space-before-9" className="text-[10px] text-base-content/40 mb-0.5 block">Space before</label>
          <input id="magtypographypanel-space-before-9"
            type="number"
            value={value.paragraphSpacingBefore}
            onChange={(e) => onChange({ paragraphSpacingBefore: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="magtypographypanel-space-after-10" className="text-[10px] text-base-content/40 mb-0.5 block">Space after</label>
          <input id="magtypographypanel-space-after-10"
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
              <label htmlFor="magtypographypanel-max-chars-per-line-0-off-11" className="text-[10px] text-base-content/40 mb-0.5 block">Max chars per line (0=off)</label>
              <input id="magtypographypanel-max-chars-per-line-0-off-11"
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
                    <label htmlFor="magtypographypanel-lines-12" className="text-[10px] text-base-content/40 mb-0.5 block">Lines</label>
                    <input id="magtypographypanel-lines-12"
                      type="number"
                      min={2}
                      max={6}
                      value={value.dropCap.lines}
                      onChange={(e) => onChange({ dropCap: { ...value.dropCap, lines: Number(e.target.value) } })}
                      className="input input-bordered input-xs w-full"
                    />
                  </div>
                  <div>
                    <label htmlFor="magtypographypanel-font-override-13" className="text-[10px] text-base-content/40 mb-0.5 block">Font override</label>
                    <input id="magtypographypanel-font-override-13"
                      type="text"
                      value={value.dropCap.font ?? ''}
                      onChange={(e) => onChange({ dropCap: { ...value.dropCap, font: e.target.value || null } })}
                      className="input input-bordered input-xs w-full"
                    />
                  </div>
                  <div>
                    <label htmlFor="magtypographypanel-color-14" className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
                    <input id="magtypographypanel-color-14"
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
                <label htmlFor="magtypographypanel-orphans-15" className="text-[10px] text-base-content/40 mb-0.5 block">Orphans</label>
                <input id="magtypographypanel-orphans-15"
                  type="number"
                  min={1}
                  max={5}
                  value={value.orphans}
                  onChange={(e) => onChange({ orphans: Number(e.target.value) })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label htmlFor="magtypographypanel-widows-16" className="text-[10px] text-base-content/40 mb-0.5 block">Widows</label>
                <input id="magtypographypanel-widows-16"
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
        <label htmlFor="magtypographypanel-paragraph-style-17" className="text-[10px] text-base-content/40 mb-0.5 block">Paragraph style</label>
        <select id="magtypographypanel-paragraph-style-17"
          value={value.paragraphStyleId ?? ''}
          onChange={(e) => {
            const id = e.target.value || null;
            const preset = PARAGRAPH_PRESETS.find(p => p.id === id);
            if (preset) onChange({ ...preset.typography, paragraphStyleId: id });
            else onChange({ paragraphStyleId: id });
          }}
          className="select select-bordered select-xs w-full"
        >
          <option value="">None</option>
          {PARAGRAPH_PRESETS.map(p => (
            <option key={p.id} value={p.id}>{p.label}</option>
          ))}
        </select>
      </div>

      {/* Character style */}
      <div>
        <label htmlFor="magtypographypanel-character-style-18" className="text-[10px] text-base-content/40 mb-0.5 block">Character style</label>
        <select id="magtypographypanel-character-style-18"
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
