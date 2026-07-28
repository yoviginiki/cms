<?php

namespace App\Domain\Migration\Services;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * "Assisted" Elementor import: connect ONCE with the caller-supplied WordPress
 * DB credentials (never stored), enumerate the Elementor-built pages, and build
 * the ready-to-run `elementor:import` command with the WP-id→slug mapping — the
 * tedious part of a migration. The DB host is locked to localhost (no SSRF) and
 * the password is emitted as a placeholder, so no secret is persisted or echoed.
 */
class ElementorImportPlanner
{
    /** Minimum `_elementor_data` length to count as a real Elementor page. */
    private const MIN_DATA_LEN = 200;

    /**
     * @param array{wp_db:string,wp_user:string,wp_pass:string,wp_prefix?:string,origin?:string} $creds
     * @return array{pages:array<int,array>,postsAvailable:int,catalogPostId:?int,command:string}
     * @throws \RuntimeException on connection/query failure (bad creds, etc.)
     */
    public function plan(Site $site, array $creds): array
    {
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($creds['wp_prefix'] ?? 'wp_')) ?: 'wp_';
        $origin = rtrim((string) ($creds['origin'] ?? ''), '/');

        Config::set('database.connections.wp_plan', [
            'driver' => 'mysql',
            'host' => '127.0.0.1', // locked to localhost — no SSRF to arbitrary hosts
            'database' => (string) $creds['wp_db'],
            'username' => (string) $creds['wp_user'],
            'password' => (string) $creds['wp_pass'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        try {
            $wp = DB::connection('wp_plan');

            $existingSlugs = Page::where('site_id', $site->id)->pluck('slug')->flip();

            $rows = $wp->table("{$prefix}posts as p")
                ->join("{$prefix}postmeta as m", function ($j) {
                    $j->on('m.post_id', '=', 'p.ID')->where('m.meta_key', '=', '_elementor_data');
                })
                ->where('p.post_status', 'publish')
                ->where('p.post_type', 'page')
                ->whereRaw('LENGTH(m.meta_value) > ?', [self::MIN_DATA_LEN])
                ->orderBy('p.ID')
                ->select('p.ID', 'p.post_name', 'p.post_title')
                ->get();

            $pages = [];
            $catalogPostId = null;
            foreach ($rows as $r) {
                $slug = (string) $r->post_name;
                if (preg_match('/(produkti-katalog|products-catalog|catalog|katalog)/i', $slug) === 1) {
                    $catalogPostId ??= (int) $r->ID;
                }
                $pages[] = [
                    'wpId' => (int) $r->ID,
                    'slug' => $slug,
                    'title' => (string) $r->post_title,
                    'matched' => $existingSlugs->has($slug),
                ];
            }

            $postsAvailable = (int) $wp->table("{$prefix}posts")
                ->where('post_type', 'post')->where('post_status', 'publish')->count();
        } finally {
            DB::purge('wp_plan');
            Config::offsetUnset('database.connections.wp_plan');
        }

        return [
            'pages' => $pages,
            'postsAvailable' => $postsAvailable,
            'catalogPostId' => $catalogPostId,
            'command' => $this->buildCommand($site, $prefix, $origin, $catalogPostId, $pages, (string) $creds['wp_db'], (string) $creds['wp_user']),
        ];
    }

    /** Ready-to-run command; password is a placeholder (never the real secret). */
    private function buildCommand(Site $site, string $prefix, string $origin, ?int $catalogPostId, array $pages, string $db, string $user): string
    {
        // Default the mapping to the pages the CMS already has (the real site,
        // as crawled by the wizard) — bloated theme-demo templates are excluded.
        // First import (nothing matched yet) → list all, the operator curates.
        $matched = array_values(array_filter($pages, fn ($p) => $p['matched']));
        $mapping = implode(',', array_map(fn ($p) => $p['wpId'] . ':' . $p['slug'], $matched !== [] ? $matched : $pages));

        $parts = [
            'php artisan elementor:import',
            '--tenant=' . $site->tenant_id,
            '--site=' . $site->slug,
            '--wp-db=' . $db,
            '--wp-user=' . $user,
            "--wp-pass='<YOUR_WP_DB_PASSWORD>'",
            '--wp-prefix=' . $prefix,
        ];
        if ($origin !== '') {
            $parts[] = '--origin=' . $origin;
        }
        if ($catalogPostId !== null) {
            $parts[] = '--catalog-post=' . $catalogPostId;
        }
        $parts[] = '--pages=' . $mapping;

        return implode(" \\\n  ", $parts);
    }
}
