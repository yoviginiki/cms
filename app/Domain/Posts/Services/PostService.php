<?php

namespace App\Domain\Posts\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PostService
{
    public function createPost(array $data, Site $site): Post
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $this->generateUniqueSlug(
            $data['slug'] ?? $data['title'], $site
        );
        $data['author_id'] = $data['author_id'] ?? Auth::id();

        // Assign default category if none specified
        if (empty($data['category_id'])) {
            $data['category_id'] = Category::where('site_id', $site->id)
                ->orderBy('sort_order')
                ->value('id');
        }

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $post = Post::create($data);

        if ($tagIds !== null) {
            $post->tags()->sync($tagIds);
        }

        return $post->load('tags');
    }

    public function updatePost(Post $post, array $data): Post
    {
        if (isset($data['slug']) && $data['slug'] !== $post->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $post->site, $post->id);

            // Remember the OLD published path for delta stale-file cleanup (§7)
            if ($post->status === 'published') {
                $seoMeta = $data['seo_meta'] ?? [];
                $oldPath = \App\Domain\Publishing\Services\LocalePaths::postPath($post->site, $post);
                $prev = ($post->seo_meta['_previous_paths'] ?? []);
                $seoMeta['_previous_paths'] = array_slice(array_values(array_unique(array_merge($prev, [$oldPath]))), -5);
                $data['seo_meta'] = $seoMeta;
            }
        }

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        // Merge seo_meta instead of replacing — prevents losing fields
        // (canvas config, custom scripts) when a partial SEO patch is sent.
        if (isset($data['seo_meta']) && is_array($data['seo_meta'])) {
            $data['seo_meta'] = array_merge($post->seo_meta ?? [], $data['seo_meta']);
        }

        // Real content edits stamp content_modified_at (F4 — accurate dateModified)
        if (array_intersect(['title', 'excerpt'], array_keys($data)) !== []) {
            $data['content_modified_at'] = now();
        }

        $post->update($data);

        if ($tagIds !== null) {
            $post->tags()->sync($tagIds);
        }

        return $post->fresh('tags');
    }

    private function generateUniqueSlug(string $text, Site $site, ?string $excludeId = null): string
    {
        $slug = \App\Support\Slugify::slug($text);
        $original = $slug;
        $count = 1;

        $query = Post::where('site_id', $site->id)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = Post::where('site_id', $site->id)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
