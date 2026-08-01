import { AssetPicker } from '@/components/ui/AssetPicker';

/**
 * Standalone asset-picker window for the inline editor.
 *
 * Opened as a popup from the web preview (overlay.js) when the user clicks an
 * editable image. Renders the real AssetPicker (browse / search / upload) and
 * hands the chosen asset back to the opener via postMessage, then closes.
 * Route: /admin/sites/:siteId/asset-picker (auth via the shared session cookie).
 */
export default function AssetPickerPopup() {
  const done = (asset: { id: string; url: string }) => {
    try {
      window.opener?.postMessage(
        { source: 'sp-asset-picker', assetId: asset.id, url: asset.url },
        window.location.origin,
      );
    } catch {
      /* opener gone — nothing to do */
    }
    window.close();
  };

  return (
    <div className="min-h-screen bg-base-200 flex items-center justify-center p-4">
      <AssetPicker open accept="image" onClose={() => window.close()} onSelect={done} />
    </div>
  );
}
