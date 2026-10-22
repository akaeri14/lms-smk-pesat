<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizEnded implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $quizId, public array $leaderboard = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'status' => 'ended',
            'leaderboard' => $this->leaderboard,
        ];
    }
}
