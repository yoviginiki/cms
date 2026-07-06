import { AssetField } from '@/components/ui/AssetPicker';

interface MediaData {
  url?: string;
  posterSrc?: string;
  posterAssetId?: string | null;
  showQr?: boolean;
  title?: string;
}

/**
 * Properties for video/audio frames: the source URL (previously missing
 * entirely — the canvas said "Set URL in Properties" but no panel existed),
 * plus for video a cover image shown in front of the embed and an optional
 * QR code of the video URL so printed pages stay scannable.
 */
export default function MediaPanel({ kind, data, onChange }: {
  kind: 'video' | 'audio';
  data: MediaData;
  onChange: (patch: Partial<MediaData>) => void;
}) {
  return (
    <div className="px-3 py-2 border-t border-base-300/20 space-y-2">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">
        {kind === 'video' ? 'Video' : 'Audio'}
      </h3>

      <div>
        <label htmlFor="media-url" className="text-[9px] text-base-content/40 block mb-0.5">
          {kind === 'video' ? 'Video URL (YouTube, Vimeo or direct .mp4)' : 'Audio URL'}
        </label>
        <input
          id="media-url"
          type="url"
          value={data.url || ''}
          onChange={(e) => onChange({ url: e.target.value.trim() })}
          placeholder={kind === 'video' ? 'https://youtu.be/…' : 'https://…/track.mp3'}
          className="input input-bordered input-xs w-full text-[11px]"
        />
      </div>

      {kind === 'audio' && (
        <div>
          <label htmlFor="media-title" className="text-[9px] text-base-content/40 block mb-0.5">Title</label>
          <input
            id="media-title"
            type="text"
            value={data.title || ''}
            onChange={(e) => onChange({ title: e.target.value })}
            className="input input-bordered input-xs w-full text-[11px]"
          />
        </div>
      )}

      {kind === 'video' && (
        <>
          <AssetField
            label="Cover image (shown in front of the video)"
            value={data.posterSrc || ''}
            accept="image"
            onChange={(url, assetId) => onChange({ posterSrc: url, posterAssetId: assetId || null })}
          />
          {data.posterSrc ? (
            <button
              type="button"
              onClick={() => onChange({ posterSrc: '', posterAssetId: null })}
              className="text-[9px] text-error/70 hover:text-error"
            >
              Remove cover image
            </button>
          ) : null}

          <label className="flex items-start gap-2 cursor-pointer pt-1">
            <input
              type="checkbox"
              checked={data.showQr === true}
              onChange={(e) => onChange({ showQr: e.target.checked })}
              className="checkbox checkbox-xs mt-0.5"
            />
            <span className="text-[10px] leading-snug text-base-content/60">
              QR code overlay
              <span className="block text-[9px] text-base-content/35">
                Prints a scannable code of the video link in the corner — readers of the
                printed page (or PDF) can scan it to watch.
              </span>
            </span>
          </label>
        </>
      )}
    </div>
  );
}
