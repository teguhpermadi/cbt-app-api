<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiCorrectionStat;
use Illuminate\Console\Command;
use romanzipp\QueueMonitor\Models\Monitor;

final class DeleteOldRecords extends Command
{
    protected $signature = 'cleanup:old-records';

    protected $description = 'Delete old records (AiCorrectionStat & QueueMonitor) older than 3 days';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(3);

        $aiStatsDeleted = AiCorrectionStat::where('created_at', '<', $cutoffDate)->delete();
        $this->info("Deleted {$aiStatsDeleted} AiCorrectionStat record(s) older than 3 days.");

        $queueMonitorDeleted = Monitor::where('created_at', '<', $cutoffDate)->delete();
        $this->info("Deleted {$queueMonitorDeleted} QueueMonitor record(s) older than 3 days.");

        $totalDeleted = $aiStatsDeleted + $queueMonitorDeleted;
        $this->info("Total deleted: {$totalDeleted} record(s).");

        return Command::SUCCESS;
    }
}
