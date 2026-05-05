<?php

namespace App\Models\Magazine;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WizardMessage extends Model
{
    use HasUuids;

    protected $table = 'mag_wizard_messages';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'session_id',
        'step',
        'role',
        'content',
        'artifact_update',
        'tokens_in',
        'tokens_out',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'artifact_update' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WizardSession::class, 'session_id');
    }
}
