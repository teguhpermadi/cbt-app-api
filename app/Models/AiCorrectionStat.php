<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiCorrectionStat extends Model
{
    use HasUlids;

    protected $fillable = [
        'exam_id',
        'batch_id',
        'provider',
        'total_jobs',
        'completed_jobs',
        'failed_jobs',
        'avg_time_per_job',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_jobs' => 'integer',
        'completed_jobs' => 'integer',
        'failed_jobs' => 'integer',
        'avg_time_per_job' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function recordJobCompletion(string $batchId, float $executionTime): void
    {
        $stat = self::where('batch_id', $batchId)->first();

        if (! $stat) {
            return;
        }

        $newCompletedCount = $stat->completed_jobs + 1;

        $totalTime = $stat->completed_jobs * ($stat->avg_time_per_job ?? $executionTime);
        $newAvgTime = ($totalTime + $executionTime) / $newCompletedCount;

        $stat->update([
            'completed_jobs' => $newCompletedCount,
            'avg_time_per_job' => round($newAvgTime, 2),
        ]);

        if ($newCompletedCount >= $stat->total_jobs) {
            $stat->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    public static function recordJobFailure(string $batchId): void
    {
        $stat = self::where('batch_id', $batchId)->first();

        if (! $stat) {
            return;
        }

        $newFailedCount = $stat->failed_jobs + 1;
        $newCompletedCount = $stat->completed_jobs;

        $stat->update([
            'failed_jobs' => $newFailedCount,
        ]);

        if (($newCompletedCount + $newFailedCount) >= $stat->total_jobs) {
            $stat->update([
                'status' => $newFailedCount === $stat->total_jobs ? 'failed' : 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_jobs === 0) {
            return 0;
        }

        return (int) round(($this->completed_jobs / $this->total_jobs) * 100);
    }

    public function getEstimatedRemainingSecondsAttribute(): ?int
    {
        if ($this->total_jobs === 0 || $this->completed_jobs >= $this->total_jobs) {
            return 0;
        }

        if (! $this->avg_time_per_job) {
            return null;
        }

        $remainingJobs = $this->total_jobs - $this->completed_jobs;

        return (int) ceil($remainingJobs * $this->avg_time_per_job);
    }
}
