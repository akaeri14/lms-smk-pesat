<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizGameStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public string $status,
        public array $participants = [],
        public ?string $message = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.game.state.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'status' => $this->status,
            'participants' => $this->participants,
            'participant_count' => count($this->participants),
            'message' => $this->message,
        ];
    }
}
