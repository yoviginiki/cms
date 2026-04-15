<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'custom_domain' => $this->custom_domain,
            'status' => $this->status,
            'seo_defaults' => $this->seo_defaults,
            'settings' => $this->settings,
            'active_theme' => $this->whenLoaded('theme'),
            'pages_count' => $this->whenCounted('pages'),
            'posts_count' => $this->whenCounted('posts'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
