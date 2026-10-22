<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public string $studentName,
        public int $currentScore,
        public int $rank,
        public array $leaderboard = [],
        public float $classAccuracy = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.score.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'student_name' => $this->studentName,
            'current_score' => $this->currentScore,
            'rank' => $this->rank,
            'leaderboard' => $this->leaderboard,
            'class_accuracy' => $this->classAccuracy,
        ];
    }
}
