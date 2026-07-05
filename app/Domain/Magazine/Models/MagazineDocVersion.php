<?php

namespace App\Domain\Magazine\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MagazineDocVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['issue_id', 'label', 'document', 'page_count', 'frame_count', 'created_at'];

    protected $casts = [
        'document' => 'array',
        'created_at' => 'datetime',
    ];
}
