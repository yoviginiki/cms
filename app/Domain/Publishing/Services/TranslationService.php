<?php

namespace App\Domain\Publishing\Services;

use App\Models\Block;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

/**
 * Creates a language version of a page or post following the LocalePaths
 * slug convention: translation of `about` into en = slug `about-en` with
 * seo_meta.locale = en. All blocks are copied so the translator starts
 * from the original layout and replaces the text.
 */
class TranslationService
{
    public function translate(Page|Post $content, string $locale, Site $site): Page|Post
    {
        $languages = LocalePaths::languages($site);
        if (!in_array($locale, $languages)) {
            throw ValidationException::withMessages([
                'locale' => ["Language '{$locale}' is not enabled for this site. Enable it in Site Settings → Languages."],
            ]);
        }

        $currentLocale = LocalePaths::contentLocale($content, $site);
        if ($locale === $currentLocale) {
            throw ValidationException::withMessages([
                'locale' => ['The content is already in this language.'],
            ]);
        }

        $base = LocalePaths::baseSlug($content->slug, $currentLocale);
        $targetSlug = $locale === LocalePaths::defaultLanguage($site) ? $base : "{$base}-{$locale}";

        $existing = $this->findBySlug($content, $site, $targetSlug);
        if ($existing) {
            throw ValidationException::withMessages([
                'locale' => ["A {$locale} version already exists (slug '{$targetSlug}')."],
            ]);
        }

        $translation = $content->replicate(['id', 'slug', 'created_at', 'updated_at', 'published_at']);
        $translation->title = $content->title . ' (' . strtoupper($locale) . ')';
        $translation->slug = $targetSlug;
        $translation->status = 'draft';
        $seoMeta = $content->seo_meta ?? [];
        $seoMeta['locale'] = $locale;
        $translation->seo_meta = $seoMeta;
        $translation->save();

        $this->copyBlocks($content, $translation);

        return $translation;
    }

    /** Existing translations of a piece of content, keyed by locale (any status). */
    public function siblings(Page|Post $content, Site $site): array
    {
        $currentLocale = LocalePaths::contentLocale($content, $site);
        $base = LocalePaths::baseSlug($content->slug, $currentLocale);
        $default = LocalePaths::defaultLanguage($site);

        $result = [];
        foreach (LocalePaths::languages($site) as $lang) {
            if ($lang === $currentLocale) continue;
            $slug = $lang === $default ? $base : "{$base}-{$lang}";
            $sibling = $this->findBySlug($content, $site, $slug);
            if ($sibling) $result[$lang] = $sibling;
        }

        return $result;
    }

    private function findBySlug(Page|Post $content, Site $site, string $slug): Page|Post|null
    {
        $query = $content instanceof Post
            ? Post::where('site_id', $site->id)
            : Page::where('site_id', $site->id);

        return $query->where('slug', $slug)->first();
    }

    public function copyBlocks(Page|Post $source, Page|Post $target): void
    {
        $blocks = Block::where('blockable_type', $source->getMorphClass())
            ->where('blockable_id', $source->getKey())
            ->orderBy('order')
            ->get();

        // Insert parents before children (FK on parent_block_id). Block ids are
        // model-generated (not fillable), so the old→new map is built as we insert.
        $idMap = [];
        $pending = $blocks->all();
        while ($pending) {
            $progress = false;
            foreach ($pending as $key => $block) {
                if ($block->parent_block_id && !isset($idMap[$block->parent_block_id])) {
                    continue; // parent not copied yet
                }
                $new = Block::create([
                    'blockable_type' => $target->getMorphClass(),
                    'blockable_id' => $target->getKey(),
                    'parent_block_id' => $block->parent_block_id ? $idMap[$block->parent_block_id] : null,
                    'type' => $block->type,
                    'data' => $block->data,
                    'order' => $block->order,
                    'style' => $block->style,
                ]);
                $idMap[$block->id] = $new->id;
                unset($pending[$key]);
                $progress = true;
            }
            if (!$progress) break; // orphaned parents — skip the remainder rather than loop forever
        }
    }
}
