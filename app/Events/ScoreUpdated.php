<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public array $leaderboard = [],
        public array $teams = [],
        public ?string $studentName = null,
        public int $currentScore = 0,
        public int $rank = 0,
        public float $classAccuracy = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'leaderboard' => $this->leaderboard,
            'teams' => $this->teams,
            'student_name' => $this->studentName,
            'current_score' => $this->currentScore,
            'rank' => $this->rank,
            'class_accuracy' => $this->classAccuracy,
        ];
    }
}
