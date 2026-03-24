<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\QuestionDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class RejectQuestionDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public QuestionDraft $draft,
        public string $reason,
        public ?string $reviewedBy = null
    ) {}

    public function handle(): void
    {
        if (! $this->draft->isPending()) {
            throw new InvalidArgumentException('Draft is not in pending status');
        }

        $this->draft->update([
            'status' => QuestionDraft::STATUS_REJECTED,
            'rejection_reason' => $this->reason,
            'reviewed_at' => now(),
            'reviewed_by' => $this->reviewedBy,
        ]);

        Log::info('QuestionDraft rejected', [
            'draft_id' => $this->draft->id,
            'reason' => $this->reason,
        ]);
    }
}
