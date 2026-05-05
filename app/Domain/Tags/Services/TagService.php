<?php

namespace App\Domain\Tags\Services;

use App\Models\Site;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagService
{
    public function createTag(array $data, Site $site): Tag
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['name'], $site);

        return Tag::create($data);
    }

    public function updateTag(Tag $tag, array $data): Tag
    {
        if (isset($data['slug']) && $data['slug'] !== $tag->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $tag->site, $tag->id);
        }

        $tag->update($data);

        return $tag->fresh();
    }

    public function deleteTag(Tag $tag): void
    {
        $tag->posts()->detach();
        $tag->delete();
    }

    public function mergeTags(Tag $source, Tag $target): Tag
    {
        // Move all taggables from source to target
        $source->posts()->each(function ($post) use ($target) {
            if (!$target->posts()->where('taggable_id', $post->id)->exists()) {
                $target->posts()->attach($post->id);
            }
        });

        $source->posts()->detach();
        $source->delete();

        return $target->fresh();
    }

    private function generateUniqueSlug(string $text, Site $site, ?string $excludeId = null): string
    {
        $slug = \App\Support\Slugify::slug($text);
        $original = $slug;
        $count = 1;

        $query = Tag::where('site_id', $site->id)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = Tag::where('site_id', $site->id)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
