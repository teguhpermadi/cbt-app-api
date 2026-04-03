<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AiCorrectionFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $examId,
        public string $userId,
        public string $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("exam.{$this->examId}.correction-finished"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AiCorrectionFinished';
    }
}
