<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizTeamScoresUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $quizId, public array $teams) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.team.scores.updated';
    }

    public function broadcastWith(): array
    {
        return ['quiz_id' => $this->quizId, 'teams' => $this->teams];
    }
}
