<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'theme_id', 'site_id', 'mode',
        'resolved_document', 'content_hash',
        'css_artifact_path', 'css_artifact_size',
    ];

    protected function casts(): array
    {
        return [
            'resolved_document' => 'array',
            'css_artifact_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
