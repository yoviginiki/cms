import { useState } from 'react';
import type { MagElementStyle } from '@/types/magazine';

interface FillStrokePanelProps {
  style: MagElementStyle;
  onChange: (v: Partial<MagElementStyle>) => void;
}

export default function FillStrokePanel({ style, onChange }: FillStrokePanelProps) {
  const [gradientEnabled, setGradientEnabled] = useState(!!style.fill?.gradient);
  const [linkCorners, setLinkCorners] = useState(
    (style.cornerRadius?.tl ?? 0) === (style.cornerRadius?.tr ?? 0) &&
    (style.cornerRadius?.tr ?? 0) === (style.cornerRadius?.br ?? 0) &&
    (style.cornerRadius?.br ?? 0) === (style.cornerRadius?.bl ?? 0)
  );

  const handleCornerChange = (corner: 'tl' | 'tr' | 'br' | 'bl', val: number) => {
    if (linkCorners) {
      onChange({ cornerRadius: { tl: val, tr: val, br: val, bl: val } });
    } else {
      const base = style.cornerRadius || { tl: 0, tr: 0, br: 0, bl: 0 };
      onChange({ cornerRadius: { ...base, [corner]: val } });
    }
  };

  return (
    <div className="space-y-3">
      {/* Fill section */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Fill</h3>

      <div className="flex gap-1 items-center">
        <input name="mag-fillstrokepanel-1"
          type="color"
          value={style.fill?.color ?? '#ffffff'}
          onChange={(e) => onChange({ fill: { ...style.fill, color: e.target.value } })}
          className="w-8 h-6 cursor-pointer rounded border border-base-300"
        />
        <input name="mag-fillstrokepanel-2"
          type="text"
          value={style.fill?.color ?? ''}
          onChange={(e) => onChange({ fill: { ...style.fill, color: e.target.value || null } })}
          className="input input-bordered input-xs flex-1"
          placeholder="Color"
        />
      </div>

      <div>
        <label htmlFor="fillstrokepanel-opacity-1" className="text-[10px] text-base-content/40 mb-0.5 block">Opacity</label>
        <input id="fillstrokepanel-opacity-1"
          type="range"
          min={0}
          max={100}
          value={Math.round(style.fill?.opacity * 100)}
          onChange={(e) => onChange({ fill: { ...style.fill, opacity: Number(e.target.value) / 100 } })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{Math.round(style.fill?.opacity * 100)}%</span>
      </div>

      {/* Gradient toggle */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input name="mag-fillstrokepanel-3"
          type="checkbox"
          checked={gradientEnabled}
          onChange={(e) => {
            setGradientEnabled(e.target.checked);
            if (!e.target.checked) {
              onChange({ fill: { ...style.fill, gradient: null } });
            } else {
              onChange({
                fill: {
                  ...style.fill,
                  gradient: { type: 'linear', angle: 0, stops: [{ offset: 0, color: '#000000' }, { offset: 1, color: '#ffffff' }] },
                },
              });
            }
          }}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Gradient</span>
      </label>

      {gradientEnabled && style.fill?.gradient && (
        <div className="space-y-2 pl-4">
          <div>
            <label htmlFor="fillstrokepanel-type-2" className="text-[10px] text-base-content/40 mb-0.5 block">Type</label>
            <select id="fillstrokepanel-type-2"
              value={style.fill?.gradient.type}
              onChange={(e) =>
                onChange({ fill: { ...style.fill, gradient: { ...style.fill?.gradient!, type: e.target.value as 'linear' | 'radial' } } })
              }
              className="select select-bordered select-xs w-full"
            >
              <option value="linear">Linear</option>
              <option value="radial">Radial</option>
            </select>
          </div>
          <div>
            <label htmlFor="fillstrokepanel-angle-3" className="text-[10px] text-base-content/40 mb-0.5 block">Angle</label>
            <input id="fillstrokepanel-angle-3"
              type="number"
              min={0}
              max={360}
              value={style.fill?.gradient.angle}
              onChange={(e) =>
                onChange({ fill: { ...style.fill, gradient: { ...style.fill?.gradient!, angle: Number(e.target.value) } } })
              }
              className="input input-bordered input-xs w-full"
            />
          </div>
          {style.fill?.gradient.stops.map((stop, i) => (
            <div key={i} className="flex gap-1 items-center">
              <input name="mag-fillstrokepanel-4"
                type="color"
                value={stop.color}
                onChange={(e) => {
                  const stops = [...style.fill?.gradient!.stops];
                  stops[i] = { ...stops[i], color: e.target.value };
                  onChange({ fill: { ...style.fill, gradient: { ...style.fill?.gradient!, stops } } });
                }}
                className="w-6 h-5 cursor-pointer rounded border border-base-300"
              />
              <input name="mag-fillstrokepanel-5"
                type="number"
                min={0}
                max={1}
                step={0.01}
                value={stop.offset}
                onChange={(e) => {
                  const stops = [...style.fill?.gradient!.stops];
                  stops[i] = { ...stops[i], offset: Number(e.target.value) };
                  onChange({ fill: { ...style.fill, gradient: { ...style.fill?.gradient!, stops } } });
                }}
                className="input input-bordered input-xs w-16"
              />
            </div>
          ))}
        </div>
      )}

      {/* Stroke section */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Stroke</h3>

      <div className="flex gap-1 items-center">
        <input name="mag-fillstrokepanel-6"
          type="color"
          value={style.stroke?.color === 'transparent' ? '#000000' : style.stroke?.color}
          onChange={(e) => onChange({ stroke: { ...style.stroke, color: e.target.value } })}
          className="w-8 h-6 cursor-pointer rounded border border-base-300"
        />
        <input name="mag-fillstrokepanel-7"
          type="text"
          value={style.stroke?.color}
          onChange={(e) => onChange({ stroke: { ...style.stroke, color: e.target.value } })}
          className="input input-bordered input-xs flex-1"
        />
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="fillstrokepanel-width-4" className="text-[10px] text-base-content/40 mb-0.5 block">Width</label>
          <input id="fillstrokepanel-width-4"
            type="number"
            min={0}
            max={20}
            value={style.stroke?.width}
            onChange={(e) => onChange({ stroke: { ...style.stroke, width: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="fillstrokepanel-style-5" className="text-[10px] text-base-content/40 mb-0.5 block">Style</label>
          <select id="fillstrokepanel-style-5"
            value={typeof style.stroke?.style === 'string' ? style.stroke?.style : 'solid'}
            onChange={(e) => onChange({ stroke: { ...style.stroke, style: e.target.value as 'solid' | 'dashed' | 'dotted' } })}
            className="select select-bordered select-xs w-full"
          >
            <option value="solid">Solid</option>
            <option value="dashed">Dashed</option>
            <option value="dotted">Dotted</option>
          </select>
        </div>
      </div>

      <div>
        <label htmlFor="fillstrokepanel-alignment-6" className="text-[10px] text-base-content/40 mb-0.5 block">Alignment</label>
        <select id="fillstrokepanel-alignment-6"
          value={style.stroke?.alignment}
          onChange={(e) => onChange({ stroke: { ...style.stroke, alignment: e.target.value as 'inside' | 'center' | 'outside' } })}
          className="select select-bordered select-xs w-full"
        >
          <option value="inside">Inside</option>
          <option value="center">Center</option>
          <option value="outside">Outside</option>
        </select>
      </div>

      {/* Corner radius */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Corner Radius</h3>

      <div className="grid grid-cols-4 gap-1">
        {(['tl', 'tr', 'br', 'bl'] as const).map((corner) => (
          <div key={corner}>
            <label className="text-[10px] text-base-content/40 mb-0.5 block">{corner.toUpperCase()}</label>
            <input name="mag-fillstrokepanel-8"
              type="number"
              min={0}
              value={style.cornerRadius?.[corner] ?? 0}
              onChange={(e) => handleCornerChange(corner, Number(e.target.value))}
              className="input input-bordered input-xs w-full"
            />
          </div>
        ))}
      </div>

      <label className="flex items-center gap-1.5 cursor-pointer">
        <input name="mag-fillstrokepanel-9"
          type="checkbox"
          checked={linkCorners}
          onChange={(e) => setLinkCorners(e.target.checked)}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Link all</span>
      </label>
    </div>
  );
}
