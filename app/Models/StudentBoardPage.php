<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentBoardPage extends Model
{
    public const MAX_PER_USER = 20;

    protected $fillable = ['user_id', 'name', 'position', 'objects'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
