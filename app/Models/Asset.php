<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'original_name', 'storage_path', 'mime_type',
        'file_size', 'dimensions', 'variants', 'checksum', 'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'variants' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
