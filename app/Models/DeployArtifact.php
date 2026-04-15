<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployArtifact extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'deployment_id', 'page_id', 'post_id',
        'output_path', 'content_hash',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
