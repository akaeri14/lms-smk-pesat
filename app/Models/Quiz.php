<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['class_id', 'title', 'description', 'duration_minutes', 'max_attempts', 'quiz_mode', 'due_at', 'randomize_options', 'bgm_path', 'time_per_question', 'enable_fifty_fifty', 'enable_freeze_time', 'is_published', 'status', 'created_by'])]
class Quiz extends Model
{
    use HasFactory;

    protected $appends = ['bgm_url'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'max_attempts' => 'integer',
            'due_at' => 'datetime',
            'randomize_options' => 'boolean',
            'time_per_question' => 'integer',
            'enable_fifty_fifty' => 'boolean',
            'enable_freeze_time' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function getBgmUrlAttribute(): ?string
    {
        return $this->bgm_path ? Storage::disk('public')->url($this->bgm_path) : null;
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }
}
