<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'short_id' => substr($this->id, 0, 8),
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'editor_mode' => $this->editor_mode,
            'experience_mode' => $this->experience_mode,
            'layout_id' => $this->layout_id,
            'category_id' => $this->category_id,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featured_image,
            'video_url' => $this->video_url,
            'thumbnail' => $this->thumbnail,
            'post_format' => $this->post_format ?? 'standard',
            'grid_id' => $this->grid_id,
            'grid' => $this->whenLoaded('grid', fn() => [
                'id' => $this->grid->id,
                'name' => $this->grid->name,
                'slug' => $this->grid->slug,
            ]),
            'category' => $this->whenLoaded('category'),
            'seo_meta' => $this->seo_meta,
            'blocks' => $this->whenLoaded('blocks'),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
