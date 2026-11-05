<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScheduleItem extends Model
{
    public const MAX_PER_USER = 120;

    public const KINDS = ['lecture', 'exam', 'lab', 'other'];

    protected $fillable = ['user_id', 'title', 'kind', 'day', 'starts_at', 'ends_at', 'room', 'color'];

    protected function casts(): array
    {
        return ['day' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,mixed> */
    public function toItem(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'kind' => $this->kind, 'day' => $this->day,
            'start' => $this->starts_at, 'end' => $this->ends_at, 'room' => $this->room, 'color' => $this->color,
        ];
    }
}
