<?php

namespace App\Domain\Projection;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Blocks\Services\BlockService;
use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * Integration layer for the projection: converts persisted block trees into
 * pure builder inputs, runs the (pure) builder, and writes the public sidecar
 * artifacts into the staging tree. I/O lives HERE, never in the builder.
 *
 * All output is gated behind the per-site crawler policy
 * (`settings.crawler_policy.projection_access`, default `none`), so a site that
 * has not opted in publishes byte-for-byte as before (publish regression).
 */
class ProjectionPublisher
{
    public const MANIFEST_VERSION = '1.0';

    public function __construct(
        private readonly ProjectionBuilder $builder,
        private readonly BlockService $blockService,
        private readonly BlockRegistry $registry,
        private readonly ProjectionParityGuard $parity,
    ) {
    }

    /** Whether this site publishes projection sidecars. Default: no. */
    public function isEnabled(Site $site): bool
    {
        return ($site->settings['crawler_policy']['projection_access'] ?? 'none') === 'public';
    }

    /**
     * Parity-check an already-built projection and write its public sidecar.
     * Returns a manifest entry on success, or a `__parity_failed` marker (no
     * file written) when the projection diverges from the HTML.
     *
     * @return array<string,mixed>
     */
    public function writeSidecarFor(Projection $projection, string $html, string $stagingPath, string $pagePath): array
    {
        $public = $projection->view(ProjectionView::Public);
        $full = $projection->toArray();
        $url = $full['page']['url'];

        $missing = $this->parity->check($public, $html);
        if ($missing !== []) {
            // Divergence must not ship (Prime Directive 4): skip the sidecar and
            // surface the offending fields to the caller.
            return ['__parity_failed' => true, 'url' => $url, 'missing' => $missing];
        }

        $sidecar = $this->sidecarPath($pagePath);
        $target = rtrim($stagingPath, '/') . '/' . ltrim($sidecar, '/');
        File::ensureDirectoryExists(dirname($target));
        File::put($target, $this->encode($public));

        return [
            'url' => $url,
            'title' => $full['page']['title'],
            'language' => $full['page']['language'],
            'projection' => '/' . ltrim($sidecar, '/'),
            'content_hash' => $full['source']['content_hash'],
        ];
    }

    /**
     * Write the site-level manifest. Parity-failed entries are excluded.
     *
     * @param list<array<string,mixed>> $entries
     * @return array<string,mixed> The written manifest.
     */
    public function writeManifest(Site $site, array $entries, string $stagingPath): array
    {
        $pages = array_values(array_filter($entries, fn ($e) => empty($e['__parity_failed'])));

        $manifest = [
            'manifest_version' => self::MANIFEST_VERSION,
            'site' => [
                'url' => '/',
                'language' => $this->siteLanguage($site),
            ],
            'pages' => $pages,
            'collections' => [],
        ];

        File::put(rtrim($stagingPath, '/') . '/manifest.json', $this->encode($manifest));

        return $manifest;
    }

    public function build(Site $site, object $content, string $url, string $pageVersionId = ''): Projection
    {
        $ctx = new ProjectionContext(
            pageId: (string) $content->id,
            pageVersionId: $pageVersionId,
            url: $url,
            title: (string) ($content->title ?? ''),
            language: $this->siteLanguage($site),
            publishedAt: optional($content->published_at ?? null)?->toIso8601String(),
            modifiedAt: optional($content->updated_at ?? null)?->toIso8601String(),
            descriptorResolver: $this->resolver(),
        );

        return $this->builder->build($this->treeFor($content), $ctx);
    }

    public function treeFor(object $content): BlockTree
    {
        /** @var array $tree */
        $tree = $this->blockService->getBlockTree($content);

        return new BlockTree(array_map(fn (array $n) => $this->toNode($n), $tree));
    }

    /** Convert one BlockService tree node into a pure BlockNode. */
    private function toNode(array $n): BlockNode
    {
        return new BlockNode(
            (string) ($n['id'] ?? ''),
            (string) ($n['type'] ?? ''),
            is_array($n['data'] ?? null) ? $n['data'] : [],
            array_map(fn (array $c) => $this->toNode($c), $n['children'] ?? []),
        );
    }

    /** @return \Closure(string):(?BlockProjection) */
    private function resolver(): \Closure
    {
        $registry = $this->registry;

        return function (string $type) use ($registry): ?BlockProjection {
            $def = $registry->get($type);

            return $def instanceof ProvidesProjection ? $def->projection() : null;
        };
    }

    /** "about/index.html" → "/about/"; "index.html" → "/". */
    public function urlForPath(string $pagePath): string
    {
        $p = preg_replace('#(?:^|/)index\.html$#', '/', $pagePath);
        $p = '/' . ltrim((string) $p, '/');

        return $p === '/' ? '/' : rtrim($p, '/') . '/';
    }

    /** "about/index.html" → "about/index.json"; other → "<path>.json". */
    public function sidecarPath(string $pagePath): string
    {
        if (str_ends_with($pagePath, 'index.html')) {
            return substr($pagePath, 0, -strlen('index.html')) . 'index.json';
        }

        return $pagePath . '.json';
    }

    private function siteLanguage(Site $site): string
    {
        return (string) ($site->settings['default_locale'] ?? $site->settings['language'] ?? 'en');
    }

    private function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
