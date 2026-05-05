<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'editor_mode' => $this->editor_mode,
            'layout_id' => $this->layout_id,
            'seo_meta' => $this->seo_meta,
            'sort_order' => $this->sort_order,
            'parent_id' => $this->parent_id,
            'blocks' => $this->whenLoaded('blocks'),
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
