<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Learning\BulkDeleteRequest;
use App\Http\Requests\Learning\ReorderRequest;
use App\Http\Requests\Learning\StoreLearningPathRequest;
use App\Http\Requests\Learning\UpdateLearningPathRequest;
use App\Http\Resources\Learning\LearningPathResource;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LearningPathController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');
        $subjectId = $request->input('subject_id');
        $classroomId = $request->input('classroom_id');
        $isPublished = $request->boolean('is_published');

        $paths = LearningPath::query()
            ->with(['subject', 'classroom', 'user', 'units'])
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($classroomId, fn ($q) => $q->where('classroom_id', $classroomId))
            ->when($request->has('is_published'), fn ($q) => $q->where('is_published', $isPublished))
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            LearningPathResource::collection($paths)->response()->getData(true),
            'Learning paths retrieved successfully'
        );
    }

    public function store(StoreLearningPathRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $maxOrder = LearningPath::where('subject_id', $data['subject_id'])
            ->where('classroom_id', $data['classroom_id'])
            ->max('order');
        $data['order'] = $maxOrder !== null ? $maxOrder + 1 : 0;

        $path = LearningPath::query()->create($data);

        return $this->created(
            new LearningPathResource($path->load(['subject', 'classroom', 'user'])),
            'Learning path created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $path = LearningPath::query()
            ->with(['subject', 'classroom', 'user', 'units.lessons'])
            ->where('id', $id)
            ->first();

        if (! $path) {
            return $this->notFound('Learning path not found');
        }

        return $this->success(
            new LearningPathResource($path),
            'Learning path retrieved successfully'
        );
    }

    public function update(UpdateLearningPathRequest $request, string $id): JsonResponse
    {
        $path = LearningPath::query()->where('id', $id)->first();

        if (! $path) {
            return $this->notFound('Learning path not found');
        }

        $path->update($request->validated());

        return $this->success(
            new LearningPathResource($path->load(['subject', 'classroom', 'user'])),
            'Learning path updated successfully'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $path = LearningPath::query()->where('id', $id)->first();

        if (! $path) {
            return $this->notFound('Learning path not found');
        }

        $path->delete();

        return $this->success(message: 'Learning path deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $paths = LearningPath::onlyTrashed()
            ->with(['subject', 'classroom', 'user'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            LearningPathResource::collection($paths)->response()->getData(true),
            'Trashed learning paths retrieved successfully'
        );
    }

    public function restore(string $id): JsonResponse
    {
        $path = LearningPath::onlyTrashed()->where('id', $id)->first();

        if (! $path) {
            return $this->notFound('Trashed learning path not found');
        }

        $path->restore();

        return $this->success(
            new LearningPathResource($path->load(['subject', 'classroom', 'user'])),
            'Learning path restored successfully'
        );
    }

    public function forceDelete(string $id): JsonResponse
    {
        $path = LearningPath::withTrashed()->where('id', $id)->first();

        if (! $path) {
            return $this->notFound('Learning path not found');
        }

        $path->forceDelete();

        return $this->success(message: 'Learning path permanently deleted');
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = LearningPath::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Learning paths permanently deleted';
        } else {
            $query->delete();
            $message = 'Learning paths soft deleted';
        }

        return $this->success(message: $message);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $items = $request->items;

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                LearningPath::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        return $this->success(message: 'Learning paths reordered successfully');
    }
}
