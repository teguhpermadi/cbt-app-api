<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiCorrectionFinishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $examId,
        public string $examTitle,
        public string $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'exam_id' => $this->examId,
            'exam_title' => $this->examTitle,
            'message' => $this->message,
            'type' => 'ai_correction_finished',
        ];
    }
}
