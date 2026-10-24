<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public int $questionId,
        public int $userId,
        public string $studentName,
        public int $answeredCount,
        public int $totalParticipants,
        public bool $isCorrect = false,
        public float $elapsedSeconds = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'question_id' => $this->questionId,
            'user_id' => $this->userId,
            'student_name' => $this->studentName,
            'answered_count' => $this->answeredCount,
            'total_participants' => $this->totalParticipants,
            'is_correct' => $this->isCorrect,
            'elapsed_seconds' => $this->elapsedSeconds,
        ];
    }
}
