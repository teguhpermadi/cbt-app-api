<?php

namespace App\Console\Commands;

use App\Models\ExamResultDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RecoverManualScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recover-manual-scores {--dry-run : Only show what would be updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover lost manual scores (Essay/Short Answer) from laravel.log';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            $this->error("Log file not found at {$logPath}");
            return;
        }

        $this->info("Reading log file: {$logPath}");

        $content = File::get($logPath);

        // Pattern: [2026-03-11 15:09:12] local.INF: (Recalculating|Calculating) {Type} Detail {detail_id}. (Existing|Current) Score: {score}
        $pattern = '/(Recalculating|Calculating) ([\w\s]+) Detail ([\w-]+)\. (Existing|Current) Score: ([\d.]+)/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            $this->warn("No recovery data found in logs.");
            return;
        }

        $recoveryData = [];
        foreach ($matches as $match) {
            $type = $match[2];
            $id = $match[3];
            $score = (float) $match[5];

            // We want the latest non-zero score if multiple entries exist
            if ($score > 0) {
                $recoveryData[$id] = [
                    'score' => $score,
                    'type' => $type
                ];
            }
        }

        if (empty($recoveryData)) {
            $this->warn("Found entries in log, but all scores were 0.");
            return;
        }

        $this->info("Found " . count($recoveryData) . " manual scores to potentially recover.");

        $dryRun = $this->option('dry-run');
        $updatedCount = 0;

        foreach ($recoveryData as $id => $data) {
            $score = $data['score'];
            $type = $data['type'];
            $detail = ExamResultDetail::find($id);

            if (!$detail) {
                $this->warn("Detail ID {$id} ({$type}) not found in database. Skipping.");
                continue;
            }

            if ($detail->score_earned >= $score) {
                $this->line("Detail ID {$id} ({$type}): Database already has score {$detail->score_earned} (Log has {$score}). Skipping.");
                continue;
            }

            $this->info("Recovering Detail ID {$id} ({$type}): {$detail->score_earned} -> {$score}");

            if (!$dryRun) {
                $detail->update([
                    'score_earned' => $score,
                    'is_correct' => ($score > 0) // Assume if there's a score it's at least partially correct
                ]);
                $updatedCount++;
            }
        }

        if (!$dryRun && $updatedCount > 0) {
            $this->info("Successfully recovered {$updatedCount} scores. YOU MUST RUN CalculateExamScoreJob manually for affected sessions to update totals.");
        } elseif ($dryRun) {
            $this->info("Dry run finished. No changes made.");
        } else {
            $this->info("No scores needed recovery.");
        }
    }
}
