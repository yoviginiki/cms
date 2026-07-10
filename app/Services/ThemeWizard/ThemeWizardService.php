<?php

namespace App\Services\ThemeWizard;

use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeWizard\WizardSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Orchestrates a Theme Wizard session: start from a reference (URL or upload),
 * refine with conversational nudges (each re-compiles a live candidate theme),
 * and accept into a real site theme (editable afterward in Theme Studio).
 */
class ThemeWizardService
{
    public function __construct(
        private ReferenceThemeService $reference,
        private ThemeNudgeEngine $nudge,
        private TokenProfileCompiler $compiler,
    ) {}

    public function startFromUrl(Site $site, User $user, string $url, ?string $hint = null): WizardSession
    {
        $result = $this->reference->fromUrl($site->tenant_id, $url, $hint);
        $open = $hint ? "Make me a theme with the feel of {$url} — {$hint}" : "Make me a theme with the feel of {$url}";
        return $this->create($site, $user, 'reference', $url, $open, $result);
    }

    public function startFromUpload(Site $site, User $user, UploadedFile $file, ?string $hint = null): WizardSession
    {
        $result = $this->reference->fromUpload($site->tenant_id, $file, $hint);
        $open = $hint ? "Make me a theme from this screenshot — {$hint}" : 'Make me a theme from this screenshot';
        return $this->create($site, $user, 'upload', null, $open, $result);
    }

    /**
     * @param array{profile:array, compiled:array, usages:array} $result
     */
    private function create(Site $site, User $user, string $source, ?string $url, string $openingLine, array $result): WizardSession
    {
        return WizardSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'user_id' => $user->id,
            'title' => $result['profile']['name'] ?? 'New theme',
            'status' => 'drafting',
            'source' => $source,
            'reference_url' => $url,
            'transcript' => [
                ['role' => 'user', 'text' => $openingLine, 'at' => now()->toIso8601String()],
                ['role' => 'assistant', 'text' => $result['profile']['design_read'] ?? '', 'at' => now()->toIso8601String()],
            ],
            'profile' => $result['profile'],
            'candidate' => $result['compiled'],
            'token_usage' => $result['usages'],
        ]);
    }

    public function nudge(WizardSession $session, string $instruction): WizardSession
    {
        if ($session->status !== 'drafting') {
            throw new RuntimeException('This theme has already been accepted.');
        }
        if (empty($session->profile)) {
            throw new RuntimeException('There is no draft to refine yet.');
        }

        $result = $this->nudge->nudge($session->tenant_id, $session->profile, $instruction);
        $compiled = $this->compiler->compile($result['profile']);

        $transcript = $session->transcript ?? [];
        $transcript[] = ['role' => 'user', 'text' => $instruction, 'at' => now()->toIso8601String()];
        $transcript[] = ['role' => 'assistant', 'text' => $result['profile']['design_read'] ?? '', 'at' => now()->toIso8601String()];

        $session->update([
            'title' => $result['profile']['name'] ?? $session->title,
            'profile' => $result['profile'],
            'candidate' => $compiled,
            'transcript' => $transcript,
            'token_usage' => array_merge($session->token_usage ?? [], $result['usages']),
        ]);

        return $session->refresh();
    }

    /** Persist the candidate as a real, editable site theme. */
    public function accept(WizardSession $session): Theme
    {
        if ($session->status === 'accepted' && $session->theme_id) {
            return Theme::findOrFail($session->theme_id);
        }
        $candidate = $session->candidate;
        if (empty($candidate['document'])) {
            throw new RuntimeException('There is no theme to save yet.');
        }

        $theme = new Theme();
        $theme->fill([
            'site_id' => $session->site_id,
            'name' => $candidate['name'] ?? ($session->title ?: 'Wizard theme'),
            'slug' => $candidate['slug'] ?? \Illuminate\Support\Str::slug(($session->title ?: 'wizard') . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4))),
            'version' => '1.0.0',
            'description' => $candidate['description'] ?? null,
            'config' => [],
            'template_path' => '',
            'manifest_json' => ['author' => 'Theme Wizard', 'wizard_session_id' => $session->id],
            'document' => $candidate['document'],
            'modes' => ['light'],
            'schema_version' => '1.0.0',
        ]);
        // is_system is not fillable — always a site-owned theme.
        $theme->save();

        $session->update(['status' => 'accepted', 'theme_id' => $theme->id]);

        return $theme;
    }

    public function abandon(WizardSession $session): void
    {
        $session->update(['status' => 'abandoned']);
    }
}
