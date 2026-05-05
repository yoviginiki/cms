<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThemeCustomizerController extends Controller
{
    public function __construct(private DesignTokenGenerator $tokenGenerator) {}

    /**
     * Get all tokens with current values (defaults + theme + customizations).
     */
    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $theme = $site->theme;
        if (!$theme) {
            return response()->json(['message' => 'No active theme'], 404);
        }

        $defaults = $this->tokenGenerator->getDefaults();
        $themeTokens = $theme->config['tokens'] ?? [];
        $customizations = DB::table('theme_customizations')
            ->where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->pluck('token_value', 'token_key')
            ->toArray();

        // Build token list with source info
        $tokens = [];
        foreach ($defaults as $key => $value) {
            $tokens[$key] = [
                'key' => $key,
                'default' => $value,
                'theme' => $themeTokens[$key] ?? null,
                'custom' => $customizations[$key] ?? null,
                'value' => $customizations[$key] ?? $themeTokens[$key] ?? $value,
                'type' => $this->getTokenType($key),
            ];
        }

        return response()->json(['data' => $tokens]);
    }

    /**
     * Save customization values.
     */
    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'tokens' => ['required', 'array'],
            'tokens.*.key' => ['required', 'string'],
            'tokens.*.value' => ['required', 'string'],
        ]);

        $theme = $site->theme;
        if (!$theme) {
            return response()->json(['message' => 'No active theme'], 404);
        }

        foreach ($request->input('tokens') as $token) {
            DB::table('theme_customizations')->updateOrInsert(
                [
                    'site_id' => $site->id,
                    'theme_id' => $theme->id,
                    'token_key' => $token['key'],
                ],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'token_value' => $token['value'],
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json(['message' => 'Customizations saved']);
    }

    /**
     * Reset all customizations to theme defaults.
     */
    public function reset(Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $theme = $site->theme;
        if (!$theme) {
            return response()->json(['message' => 'No active theme'], 404);
        }

        DB::table('theme_customizations')
            ->where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->delete();

        return response()->json(['message' => 'Customizations reset to defaults']);
    }

    /**
     * Export customizations as JSON preset.
     */
    public function export(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $theme = $site->theme;
        $customizations = DB::table('theme_customizations')
            ->where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->pluck('token_value', 'token_key')
            ->toArray();

        return response()->json(['data' => [
            'theme' => $theme->name,
            'version' => $theme->version ?? '1.0.0',
            'tokens' => $customizations,
        ]]);
    }

    /**
     * Import customizations from a JSON preset.
     */
    public function import(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate(['tokens' => ['required', 'array']]);

        $theme = $site->theme;

        DB::table('theme_customizations')
            ->where('site_id', $site->id)
            ->where('theme_id', $theme->id)
            ->delete();

        foreach ($request->input('tokens') as $key => $value) {
            DB::table('theme_customizations')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'site_id' => $site->id,
                'theme_id' => $theme->id,
                'token_key' => $key,
                'token_value' => $value,
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Preset imported']);
    }

    /**
     * Generate CSS preview (for live customizer iframe).
     */
    public function previewCss(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $css = $this->tokenGenerator->generate($site);

        return response()->json(['data' => ['css' => $css]]);
    }

    private function getTokenType(string $key): string
    {
        if (str_starts_with($key, 'color-')) return 'color';
        if (str_starts_with($key, 'font-size') || str_starts_with($key, 'font-weight') || str_starts_with($key, 'line-height') || str_starts_with($key, 'letter-spacing')) return 'text';
        if (str_starts_with($key, 'font-')) return 'font';
        if (str_starts_with($key, 'space-') || str_starts_with($key, 'container-') || str_starts_with($key, 'grid-') || str_starts_with($key, 'border-radius')) return 'dimension';
        if (str_starts_with($key, 'shadow-')) return 'shadow';
        if (str_starts_with($key, 'transition-')) return 'transition';
        return 'text';
    }
}
