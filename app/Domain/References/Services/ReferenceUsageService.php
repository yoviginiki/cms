<?php

namespace App\Domain\References\Services;

use App\Models\EntityReference;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\ThemeTemplate;

/**
 * Inbound-usage lookup for delete protection and "Used on N pages" UI:
 * who references this entity?
 */
class ReferenceUsageService
{
    /**
     * @return array{count: int, sources: array<int, array{type: string, id: string, title: string, kind: string}>}
     */
    public function usage(Site $site, string $targetType, string $targetId): array
    {
        $edges = EntityReference::where('site_id', $site->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->get(['source_type', 'source_id', 'kind']);

        $pageTitles = Page::whereIn('id', $edges->where('source_type', 'page')->pluck('source_id'))
            ->pluck('title', 'id');
        $postTitles = Post::whereIn('id', $edges->where('source_type', 'post')->pluck('source_id'))
            ->pluck('title', 'id');
        $templateNames = ThemeTemplate::whereIn('id', $edges->where('source_type', 'template')->pluck('source_id'))
            ->pluck('name', 'id');

        $sources = $edges->map(fn (EntityReference $edge) => [
            'type' => $edge->source_type,
            'id' => $edge->source_id,
            'title' => match ($edge->source_type) {
                'page' => $pageTitles[$edge->source_id] ?? 'Unknown page',
                'post' => $postTitles[$edge->source_id] ?? 'Unknown post',
                'template' => $templateNames[$edge->source_id] ?? 'Unknown template',
                'site' => 'Site-wide (header/footer/theme)',
                default => ucfirst($edge->source_type),
            },
            'kind' => $edge->kind,
        ])->values()->all();

        return ['count' => count($sources), 'sources' => $sources];
    }
}
