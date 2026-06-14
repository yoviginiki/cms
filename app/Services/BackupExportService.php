<?php

namespace App\Services;

use App\Models\Site;

class BackupExportService
{
    public function __construct(
        private ActivityLogService $activityLog,
    ) {}

    /**
     * Export full site backup as JSON structure.
     * Does NOT include secrets, credentials, or absolute paths.
     */
    public function export(Site $site): array
    {
        $site->load([
            'pages' => fn($q) => $q->withTrashed()->with('blocks'),
            'posts' => fn($q) => $q->with('blocks', 'tags', 'category'),
            'theme',
        ]);

        $manifest = [
            'schema_version' => '1.0.0',
            'cms_version' => '1.0.0',
            'exported_at' => now()->toIso8601String(),
            'site' => [
                'name' => $site->name,
                'slug' => $site->slug,
                'status' => $site->status,
                'settings' => $this->sanitizeSettings($site->settings ?? []),
                'seo_defaults' => $site->seo_defaults ?? [],
            ],
            'theme' => $site->theme ? [
                'name' => $site->theme->name,
                'slug' => $site->theme->slug,
                'is_system' => $site->theme->is_system,
            ] : null,
            'pages' => $site->pages->map(fn($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'status' => $p->status,
                'seo_meta' => $p->seo_meta,
                'blocks' => $p->blocks->map(fn($b) => [
                    'type' => $b->type,
                    'level' => $b->level,
                    'order' => $b->order,
                    'data' => $b->data,
                    'parent_id' => $b->parent_id,
                ])->toArray(),
                'deleted_at' => $p->deleted_at?->toIso8601String(),
            ])->toArray(),
            'posts' => $site->posts->map(fn($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'status' => $p->status,
                'featured_image' => $p->featured_image,
                'seo_meta' => $p->seo_meta,
                'category' => $p->category?->slug,
                'tags' => $p->tags->pluck('name')->toArray(),
                'published_at' => $p->published_at?->toIso8601String(),
                'blocks' => $p->blocks->map(fn($b) => [
                    'type' => $b->type,
                    'level' => $b->level,
                    'order' => $b->order,
                    'data' => $b->data,
                    'parent_id' => $b->parent_id,
                ])->toArray(),
            ])->toArray(),
            'menus' => $site->menus()->get()->map(fn($m) => [
                'name' => $m->name,
                'slug' => $m->slug,
                'items' => $m->items,
            ])->toArray(),
            'redirects' => $site->redirects()->get()->map(fn($r) => [
                'source_path' => $r->source_path,
                'target_url' => $r->target_url,
                'status_code' => $r->status_code,
            ])->toArray(),
            'section_templates' => \App\Models\BlockTemplate::where('site_id', $site->id)
                ->get()->map(fn($t) => [
                    'name' => $t->name,
                    'category' => $t->category,
                    'blocks_data' => $t->blocks_data,
                ])->toArray(),
            'stats' => [
                'pages_count' => $site->pages->count(),
                'posts_count' => $site->posts->count(),
            ],
        ];

        $this->activityLog->log('backup.exported', $site->id, 'site', $site->id, [
            'pages_count' => $manifest['stats']['pages_count'],
            'posts_count' => $manifest['stats']['posts_count'],
        ]);

        return $manifest;
    }

    /**
     * Validate a backup manifest for restore dry-run.
     * No database writes.
     */
    public function validateForRestore(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        if (empty($manifest['schema_version'])) $errors[] = 'Missing schema_version';
        if (empty($manifest['exported_at'])) $errors[] = 'Missing exported_at';
        if (empty($manifest['site']['name'])) $errors[] = 'Missing site name';
        if (empty($manifest['site']['slug'])) $errors[] = 'Missing site slug';

        // Check for secrets
        $json = json_encode($manifest);
        if (str_contains($json, 'api_key')) $errors[] = 'Manifest contains API keys';
        if (str_contains($json, '../')) $errors[] = 'Path traversal detected';

        // Validate pages
        if (!empty($manifest['pages'])) {
            foreach ($manifest['pages'] as $i => $page) {
                if (empty($page['title']) && empty($page['slug'])) {
                    $warnings[] = "Page {$i}: missing title and slug";
                }
            }
        }

        // Check schema version compatibility
        $supported = ['1.0.0'];
        if (!empty($manifest['schema_version']) && !in_array($manifest['schema_version'], $supported)) {
            $warnings[] = "Schema version {$manifest['schema_version']} may not be fully compatible";
        }

        return [
            'can_restore' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                'pages' => count($manifest['pages'] ?? []),
                'posts' => count($manifest['posts'] ?? []),
                'menus' => count($manifest['menus'] ?? []),
                'redirects' => count($manifest['redirects'] ?? []),
            ],
        ];
    }

    private function sanitizeSettings(array $settings): array
    {
        $forbidden = ['anthropic_api_key', 'openai_api_key', 'deploy_ssh_key'];
        return array_diff_key($settings, array_flip($forbidden));
    }
}
