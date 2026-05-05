<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeAssignment extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'site_id', 'theme_id', 'mode',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
