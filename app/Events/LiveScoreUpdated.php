<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveScoreUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $examId,
        public array $sessionData
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("exam.{$this->examId}.live-score"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LiveScoreUpdated';
    }
}
