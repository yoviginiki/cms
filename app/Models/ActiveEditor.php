<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveEditor extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'page_id', 'post_id', 'last_heartbeat', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
