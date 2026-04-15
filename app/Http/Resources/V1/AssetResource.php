<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $baseUrl = "/api/v1/sites/{$this->site_id}/assets/{$this->id}/serve";

        $variantUrls = [];
        foreach ($this->variants ?? [] as $key => $path) {
            $variantUrls[$key] = "{$baseUrl}/{$key}";
        }

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'dimensions' => $this->dimensions,
            'alt_text' => $this->alt_text,
            'url' => $baseUrl,
            'variants' => $variantUrls,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
