<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueueMonitorResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use romanzipp\QueueMonitor\Models\Monitor;

class QueueMonitorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 15);
        $search  = $request->input('search');
        $status  = $request->input('status'); // 'running' | 'succeeded' | 'failed'

        $query = Monitor::query()->ordered();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('job_id', 'like', "%{$search}%")
                  ->orWhere('data', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            match ($status) {
                'running'   => $query->whereIn('status', [0, 4]), // RUNNING + QUEUED
                'succeeded' => $query->whereIn('status', [1, 3]), // SUCCEEDED + STALE
                'failed'    => $query->where('status', 2),        // FAILED
                default     => null,
            };
        }

        $monitors = $query->paginate($perPage);

        return QueueMonitorResource::collection($monitors);
    }

    public function show(Monitor $monitor): QueueMonitorResource
    {
        return new QueueMonitorResource($monitor);
    }

    public function destroy(Monitor $monitor): Response
    {
        $monitor->delete();

        return response()->noContent();
    }

    public function retry(Monitor $monitor): Response|JsonResponse
    {
        if (!$monitor->canBeRetried()) {
            return response()->json(['message' => 'This job cannot be retried.'], 400);
        }

        try {
            $monitor->retry();
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function cancel(Monitor $monitor): Response|JsonResponse
    {
        // Allow cancelling both Running (0) and Queued (4) jobs
        if (!in_array($monitor->status, [0, 4])) {
            return response()->json(['message' => 'Only running or queued jobs can be cancelled.'], 400);
        }

        try {
            $monitor->update([
                'status'            => 2,
                'finished_at'       => now(),
                'exception_message' => 'Cancelled by user.',
                'exception_class'   => 'UserCancelledException',
            ]);

            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function purge(): Response
    {
        Monitor::query()->delete();

        return response()->noContent();
    }
}
