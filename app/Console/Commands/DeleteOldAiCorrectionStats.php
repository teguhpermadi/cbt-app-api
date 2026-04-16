<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiCorrectionStat;
use Illuminate\Console\Command;

final class DeleteOldAiCorrectionStats extends Command
{
    protected $signature = 'cleanup:ai-correction-stats';

    protected $description = 'Delete AiCorrectionStat records older than 3 days';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(3);

        $deleted = AiCorrectionStat::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deleted} AiCorrectionStat record(s) older than 3 days.");

        return Command::SUCCESS;
    }
}
