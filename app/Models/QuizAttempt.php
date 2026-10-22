<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_id', 'user_id', 'score', 'dynamic_score', 'total_correct', 'total_questions', 'time_taken_seconds', 'answers', 'answer_times', 'powerups_used', 'completed_at'])]
class QuizAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'dynamic_score' => 'integer',
            'total_correct' => 'integer',
            'total_questions' => 'integer',
            'time_taken_seconds' => 'integer',
            'answers' => 'array',
            'answer_times' => 'array',
            'powerups_used' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
