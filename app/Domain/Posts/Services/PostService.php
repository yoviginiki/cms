<?php

namespace App\Domain\Posts\Services;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Str;

class PostService
{
    public function createPost(array $data, Site $site): Post
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['title'], $site);

        return Post::create($data);
    }

    public function updatePost(Post $post, array $data): Post
    {
        if (isset($data['slug']) && $data['slug'] !== $post->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $post->site, $post->id);
        }

        $post->update($data);

        return $post->fresh();
    }

    private function generateUniqueSlug(string $text, Site $site, ?string $excludeId = null): string
    {
        $slug = Str::slug($text);
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
