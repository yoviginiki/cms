import React, { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { AssetField } from '@/components/ui/AssetPicker';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

export const ScrollPageEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as any;
  const pages = data.pages || [];
  const [activeTab, setActiveTab] = useState<string>('palette');

  const updateField = (path: string, value: unknown) => {
    const keys = path.split('.');
    const newData = JSON.parse(JSON.stringify(data));
    let obj = newData;
    for (let i = 0; i < keys.length - 1; i++) {
      if (!obj[keys[i]]) obj[keys[i]] = {};
      obj = obj[keys[i]];
    }
    obj[keys[keys.length - 1]] = value;
    onUpdate(newData);
  };

  const tabs = [
    { key: 'palette', label: 'Colors' },
    { key: 'typography', label: 'Type' },
    { key: 'backdrop', label: 'Backdrop' },
    { key: 'mouseEffect', label: 'Mouse' },
    { key: 'layout', label: 'Layout' },
    { key: 'reveal', label: 'Reveal' },
  ];

  const pal = data.palette || {};
  const typo = data.typography || {};
  const back = data.backdrop || {};
  const me = data.mouseEffect || {};
  const lay = data.layout || {};
  const rev = data.reveal || {};

  return (
    <div className="space-y-3">
      {/* Tab switcher */}
      <div className="flex flex-wrap gap-1">
        {tabs.map(t => (
          <button key={t.key} onClick={() => setActiveTab(t.key)}
            className={`px-2 py-1 text-[10px] rounded font-medium ${activeTab === t.key ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'}`}>
            {t.label}
          </button>
        ))}
      </div>

      {/* Palette tab */}
      {activeTab === 'palette' && (
        <div className="space-y-2">
          {Object.entries(pal).map(([key, val]) => (
            <div key={key} className="flex items-center gap-2">
              <input type="color" value={String(val)} onChange={e => updateField(`palette.${key}`, e.target.value)}
                className="w-7 h-6 rounded border cursor-pointer" />
              <span className="text-[10px] text-gray-500 flex-1">{key}</span>
              <span className="text-[9px] font-mono text-gray-400">{String(val)}</span>
            </div>
          ))}
        </div>
      )}

      {/* Typography tab */}
      {activeTab === 'typography' && (
        <div className="space-y-2">
          <div><label className="text-[10px] text-gray-500 block">Display Font</label>
            <input type="text" value={typo.fontDisplay || ''} onChange={e => updateField('typography.fontDisplay', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
          <div><label className="text-[10px] text-gray-500 block">Body Font</label>
            <input type="text" value={typo.fontBody || ''} onChange={e => updateField('typography.fontBody', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
          <div><label className="text-[10px] text-gray-500 block">Google Fonts URL</label>
            <input type="text" value={typo.googleFontsUrl || ''} onChange={e => updateField('typography.googleFontsUrl', e.target.value)} className="input input-bordered input-xs w-full text-[9px] font-mono" /></div>
          <div><label className="text-[10px] text-gray-500 block">Base Font Size</label>
            <input type="text" value={typo.baseFontSize || '18px'} onChange={e => updateField('typography.baseFontSize', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
          <div><label className="text-[10px] text-gray-500 block">Line Height: {typo.bodyLineHeight}</label>
            <input type="range" min={1} max={2.5} step={0.05} value={typo.bodyLineHeight || 1.7} onChange={e => updateField('typography.bodyLineHeight', Number(e.target.value))} className="range range-xs w-full" /></div>
          <div><label className="text-[10px] text-gray-500 block">Max Reading Width</label>
            <input type="text" value={typo.maxReading || '36rem'} onChange={e => updateField('typography.maxReading', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
        </div>
      )}

      {/* Backdrop tab */}
      {activeTab === 'backdrop' && (
        <div className="space-y-3">
          <div><label className="text-[10px] text-gray-500 block">Paper Color</label>
            <div className="flex gap-2"><input type="color" value={back.paperColor || '#EFE7D5'} onChange={e => updateField('backdrop.paperColor', e.target.value)} className="w-8 h-6 rounded border cursor-pointer" />
              <input type="text" value={back.paperColor || ''} onChange={e => updateField('backdrop.paperColor', e.target.value)} className="input input-bordered input-xs flex-1 font-mono text-[10px]" /></div></div>

          {/* Background Image */}
          <div className="border border-base-300/30 rounded-lg p-2 space-y-2">
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={back.image?.enabled ?? false} onChange={e => updateField('backdrop.image.enabled', e.target.checked)} className="checkbox checkbox-xs" />
              <span className="text-[11px] font-medium">Background Image</span>
            </label>
            {back.image?.enabled && (
              <>
                <AssetField
                  label="Upload or choose image"
                  value={back.image?.url || ''}
                  onChange={(url, assetId) => {
                    const newData = JSON.parse(JSON.stringify(data));
                    if (!newData.backdrop) newData.backdrop = {};
                    if (!newData.backdrop.image) newData.backdrop.image = {};
                    newData.backdrop.image.url = url;
                    newData.backdrop.image.assetId = assetId || null;
                    onUpdate(newData);
                  }}
                  accept="image"
                />
                {back.image?.url && (
                  <div className="space-y-1">
                    <div><label className="text-[10px] text-gray-500 block">Base Blur: {back.image?.baseBlur}</label>
                      <input type="text" value={back.image?.baseBlur || '10px'} onChange={e => updateField('backdrop.image.baseBlur', e.target.value)} className="input input-bordered input-xs w-full text-[10px]" /></div>
                    <div><label className="text-[10px] text-gray-500 block">Saturation: {back.image?.baseSaturate}</label>
                      <input type="range" min={0} max={2} step={0.01} value={back.image?.baseSaturate ?? 0.88} onChange={e => updateField('backdrop.image.baseSaturate', Number(e.target.value))} className="range range-xs w-full" /></div>
                    <div><label className="text-[10px] text-gray-500 block">Overlay Opacity: {back.image?.overlayOpacity}</label>
                      <input type="range" min={0} max={1} step={0.01} value={back.image?.overlayOpacity ?? 0.56} onChange={e => updateField('backdrop.image.overlayOpacity', Number(e.target.value))} className="range range-xs w-full" /></div>
                    <div><label className="text-[10px] text-gray-500 block">Fit</label>
                      <select value={back.image?.fit || 'cover'} onChange={e => updateField('backdrop.image.fit', e.target.value)} className="select select-bordered select-xs w-full text-[10px]">
                        <option value="cover">Cover</option><option value="contain">Contain</option><option value="auto">Original</option>
                      </select></div>
                  </div>
                )}
              </>
            )}
          </div>

          {/* SVG Blobs */}
          <label className="flex items-center gap-2"><input type="checkbox" checked={back.svgBlobs?.enabled ?? true} onChange={e => updateField('backdrop.svgBlobs.enabled', e.target.checked)} className="checkbox checkbox-xs" /><span className="text-[10px]">SVG Blobs</span></label>

          {/* Grain */}
          <label className="flex items-center gap-2"><input type="checkbox" checked={back.grain?.enabled ?? true} onChange={e => updateField('backdrop.grain.enabled', e.target.checked)} className="checkbox checkbox-xs" /><span className="text-[10px]">Grain</span></label>
          {back.grain?.enabled && (
            <div><label className="text-[10px] text-gray-500 block">Grain Opacity: {back.grain?.opacity}</label>
              <input type="range" min={0} max={1} step={0.05} value={back.grain?.opacity ?? 0.25} onChange={e => updateField('backdrop.grain.opacity', Number(e.target.value))} className="range range-xs w-full" /></div>
          )}

          {/* Vignette */}
          <label className="flex items-center gap-2"><input type="checkbox" checked={back.vignette?.enabled ?? true} onChange={e => updateField('backdrop.vignette.enabled', e.target.checked)} className="checkbox checkbox-xs" /><span className="text-[10px]">Vignette</span></label>
        </div>
      )}

      {/* Mouse effect tab */}
      {activeTab === 'mouseEffect' && (
        <div className="space-y-2">
          <label className="flex items-center gap-2"><input type="checkbox" checked={me.enabled ?? true} onChange={e => updateField('mouseEffect.enabled', e.target.checked)} className="checkbox checkbox-xs" /><span className="text-[11px]">Enable mouse effect</span></label>
          {me.enabled && <>
            <div><label className="text-[10px] text-gray-500 block">Preset</label>
              <select value={me.preset || 'just-clouds'} onChange={e => updateField('mouseEffect.preset', e.target.value)} className="select select-bordered select-xs w-full text-[11px]">
                <option value="just-clouds">Clouds (soft light)</option>
                <option value="just-water">Water (global ripple)</option>
                <option value="just-swirls">Swirls (local stir)</option>
                <option value="water-ink">Water Ink (needs image)</option>
              </select></div>
            <div><label className="text-[10px] text-gray-500 block">Cursor</label>
              <select value={me.cursor?.shape || 'circle-dot'} onChange={e => updateField('mouseEffect.cursor.shape', e.target.value)} className="select select-bordered select-xs w-full text-[11px]">
                <option value="none">None</option>
                <option value="os-default">OS Default</option>
                <option value="circle">Circle</option>
                <option value="dot">Dot</option>
                <option value="circle-dot">Circle + Dot</option>
              </select></div>
            <div><label className="text-[10px] text-gray-500 block">Radius: {me.radius}</label>
              <input type="text" value={me.radius || '310px'} onChange={e => updateField('mouseEffect.radius', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>

            {/* Preset-specific settings */}
            {me.preset === 'just-clouds' && (
              <div className="border border-base-300/30 rounded-lg p-2 space-y-1 mt-1">
                <span className="text-[10px] font-medium text-gray-600">Clouds Settings</span>
                <div><label className="text-[10px] text-gray-500 block">Softness</label>
                  <input type="text" value={me['just-clouds']?.softness || '180px'} onChange={e => updateField('mouseEffect.just-clouds.softness', e.target.value)} className="input input-bordered input-xs w-full text-[10px]" /></div>
                <div><label className="text-[10px] text-gray-500 block">Lighten: {me['just-clouds']?.lightenAmount ?? 0.15}</label>
                  <input type="range" min={0} max={0.5} step={0.01} value={me['just-clouds']?.lightenAmount ?? 0.15} onChange={e => updateField('mouseEffect.just-clouds.lightenAmount', Number(e.target.value))} className="range range-xs w-full" /></div>
              </div>
            )}

            {me.preset === 'water-ink' && (
              <div className="border border-base-300/30 rounded-lg p-2 space-y-1 mt-1">
                <span className="text-[10px] font-medium text-gray-600">Water Ink Settings</span>
                <div><label className="text-[10px] text-gray-500 block">Displacement: {me['water-ink']?.displacementScale ?? 120}</label>
                  <input type="range" min={20} max={300} step={10} value={me['water-ink']?.displacementScale ?? 120} onChange={e => updateField('mouseEffect.water-ink.displacementScale', Number(e.target.value))} className="range range-xs w-full" /></div>
                <div><label className="text-[10px] text-gray-500 block">Turbulence: {me['water-ink']?.turbulenceFreq ?? 0.015}</label>
                  <input type="range" min={0.002} max={0.05} step={0.001} value={me['water-ink']?.turbulenceFreq ?? 0.015} onChange={e => updateField('mouseEffect.water-ink.turbulenceFreq', Number(e.target.value))} className="range range-xs w-full" /></div>
                <div><label className="text-[10px] text-gray-500 block">Reveal Opacity: {me['water-ink']?.revealOpacity ?? 0.85}</label>
                  <input type="range" min={0.1} max={1} step={0.05} value={me['water-ink']?.revealOpacity ?? 0.85} onChange={e => updateField('mouseEffect.water-ink.revealOpacity', Number(e.target.value))} className="range range-xs w-full" /></div>
              </div>
            )}

            {me.preset === 'just-water' && (
              <div className="border border-base-300/30 rounded-lg p-2 space-y-1 mt-1">
                <span className="text-[10px] font-medium text-gray-600">Water Settings</span>
                <div><label className="text-[10px] text-gray-500 block">Displacement: {me['just-water']?.displacementScale ?? 30}</label>
                  <input type="range" min={5} max={100} step={5} value={me['just-water']?.displacementScale ?? 30} onChange={e => updateField('mouseEffect.just-water.displacementScale', Number(e.target.value))} className="range range-xs w-full" /></div>
                <div><label className="text-[10px] text-gray-500 block">Turbulence: {me['just-water']?.turbulenceFreq ?? 0.008}</label>
                  <input type="range" min={0.002} max={0.03} step={0.001} value={me['just-water']?.turbulenceFreq ?? 0.008} onChange={e => updateField('mouseEffect.just-water.turbulenceFreq', Number(e.target.value))} className="range range-xs w-full" /></div>
              </div>
            )}

            {me.preset === 'just-swirls' && (
              <div className="border border-base-300/30 rounded-lg p-2 space-y-1 mt-1">
                <span className="text-[10px] font-medium text-gray-600">Swirl Settings</span>
                <div><label className="text-[10px] text-gray-500 block">Displacement: {me['just-swirls']?.displacementScale ?? 60}</label>
                  <input type="range" min={10} max={150} step={5} value={me['just-swirls']?.displacementScale ?? 60} onChange={e => updateField('mouseEffect.just-swirls.displacementScale', Number(e.target.value))} className="range range-xs w-full" /></div>
                <div><label className="text-[10px] text-gray-500 block">Turbulence: {me['just-swirls']?.turbulenceFreq ?? 0.02}</label>
                  <input type="range" min={0.005} max={0.05} step={0.001} value={me['just-swirls']?.turbulenceFreq ?? 0.02} onChange={e => updateField('mouseEffect.just-swirls.turbulenceFreq', Number(e.target.value))} className="range range-xs w-full" /></div>
              </div>
            )}
          </>}
        </div>
      )}

      {/* Layout tab */}
      {activeTab === 'layout' && (
        <div className="space-y-2">
          <div><label className="text-[10px] text-gray-500 block">Section Padding</label>
            <input type="text" value={lay.sectionPadding || '8rem 1.5rem'} onChange={e => updateField('layout.sectionPadding', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
          <div><label className="text-[10px] text-gray-500 block">Section Min Height</label>
            <input type="text" value={lay.sectionMinHeight || '100vh'} onChange={e => updateField('layout.sectionMinHeight', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
          <div><label className="text-[10px] text-gray-500 block">Text Align</label>
            <select value={lay.defaultTextAlign || 'center'} onChange={e => updateField('layout.defaultTextAlign', e.target.value)} className="select select-bordered select-xs w-full text-[11px]">
              <option value="center">Center</option><option value="left">Left</option><option value="right">Right</option>
            </select></div>
        </div>
      )}

      {/* Reveal tab */}
      {activeTab === 'reveal' && (
        <div className="space-y-2">
          <label className="flex items-center gap-2"><input type="checkbox" checked={rev.enabled ?? true} onChange={e => updateField('reveal.enabled', e.target.checked)} className="checkbox checkbox-xs" /><span className="text-[11px]">Enable reveal animations</span></label>
          {rev.enabled && <>
            <div><label className="text-[10px] text-gray-500 block">Duration</label>
              <input type="text" value={rev.duration || '2.4s'} onChange={e => updateField('reveal.duration', e.target.value)} className="input input-bordered input-xs w-full text-[11px]" /></div>
            <div><label className="text-[10px] text-gray-500 block">Stagger (ms): {rev.staggerMs}</label>
              <input type="range" min={0} max={1000} step={50} value={rev.staggerMs || 250} onChange={e => updateField('reveal.staggerMs', Number(e.target.value))} className="range range-xs w-full" /></div>
          </>}
        </div>
      )}

      <p className="text-[9px] text-gray-400 mt-2">{pages.length} pages · Edit content in the block preview above</p>
      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(block.data as any).effects || {}}
          onChange={(v: CardEffects) => updateField('effects', v)}
        />
      </div>
    </div>
  );
};
