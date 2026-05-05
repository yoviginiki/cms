<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Theme\Services\ThemeManager;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function __construct(private ThemeManager $themeManager) {}

    /**
     * List all available themes for a site.
     */
    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json(['data' => $this->themeManager->listForSite($site)]);
    }

    /**
     * Upload and install a theme from ZIP.
     */
    public function upload(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:zip'],
        ]);

        try {
            $theme = $this->themeManager->installFromZip($site, $request->file('file'));

            return response()->json([
                'data' => [
                    'id' => $theme->id,
                    'name' => $theme->name,
                    'version' => $theme->version,
                    'message' => "Theme \"{$theme->name}\" installed successfully.",
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Activate a theme.
     */
    public function activate(Site $site, Theme $theme): JsonResponse
    {
        $this->authorize('update', $site);

        $this->themeManager->activate($site, $theme);

        return response()->json([
            'data' => ['message' => "Theme \"{$theme->name}\" activated. Publish your site to apply changes."],
        ]);
    }

    /**
     * Export a theme as ZIP download.
     */
    public function export(Site $site, Theme $theme): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $site);

        $zipPath = $this->themeManager->exportAsZip($theme);
        $filename = \Illuminate\Support\Str::slug($theme->name) . '-v' . ($theme->version ?? '1.0') . '.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Delete a theme.
     */
    public function destroy(Site $site, Theme $theme): JsonResponse
    {
        $this->authorize('update', $site);

        if ($site->active_theme_id === $theme->id) {
            return response()->json(['message' => 'Cannot delete the active theme. Activate another theme first.'], 422);
        }

        try {
            $this->themeManager->delete($theme);
            return response()->json(null, 204);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
