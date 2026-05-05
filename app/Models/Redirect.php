<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redirect extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'source_path', 'target_url',
        'status_code', 'is_regex', 'hit_count',
    ];

    protected function casts(): array
    {
        return [
            'is_regex' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
