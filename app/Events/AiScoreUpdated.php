<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $examId,
        public string $examResultDetailId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("exam.{$this->examId}.ai-correction"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AiScoreUpdated';
    }
}
