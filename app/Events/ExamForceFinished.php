<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamForceFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $examId,
        public string $userId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("exam.{$this->examId}.user.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ExamForceFinished';
    }
}
