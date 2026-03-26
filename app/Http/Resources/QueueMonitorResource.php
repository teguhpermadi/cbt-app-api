<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use romanzipp\QueueMonitor\Models\Monitor;

/**
 * @mixin Monitor
 */
class QueueMonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job_uuid' => $this->job_uuid,
            'name' => $this->getBasename(),
            'queue' => $this->queue,
            'status' => $this->status,
            'attempt' => $this->attempt,
            'progress' => $this->progress,
            'exception_message' => $this->exception_message,
            'exception_class' => $this->exception_class,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'time_elapsed' => $this->getElapsedSeconds(),
            'failed' => $this->hasFailed(),
            'retried' => $this->retried,
            'data' => $this->getData(),
        ];
    }
}
