<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrownTransferred implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quizId,
        public ?int $holderId,
        public ?string $holderName,
        public ?int $previousHolderId,
        public string $reason,
        public int $score = 0,
        public ?string $holderAvatar = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("quiz.{$this->quizId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id' => $this->quizId,
            'holder_id' => $this->holderId,
            'holder_name' => $this->holderName,
            'holder_avatar' => $this->holderAvatar,
            'previous_holder_id' => $this->previousHolderId,
            'reason' => $this->reason,
            'score' => $this->score,
            'multiplier' => 2,
        ];
    }
}
