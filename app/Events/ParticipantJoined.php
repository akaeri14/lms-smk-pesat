<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantJoined implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public array $participant,
        public int $participantCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastAs(): string
    {
        return 'quiz.participant.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'participant' => $this->participant,
            'participant_count' => $this->participantCount,
        ];
    }
}
