<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Learning\BulkDeleteRequest;
use App\Http\Requests\Learning\ReorderRequest;
use App\Http\Requests\Learning\StoreLearningUnitRequest;
use App\Http\Requests\Learning\UpdateLearningUnitRequest;
use App\Http\Resources\Learning\LearningUnitResource;
use App\Models\LearningUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LearningUnitController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $learningPathId = $request->input('learning_path_id');
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'order');
        $order = $request->string('order', 'asc');

        $units = LearningUnit::query()
            ->with(['learningPath', 'lessons'])
            ->when($learningPathId, fn ($q) => $q->where('learning_path_id', $learningPathId))
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            LearningUnitResource::collection($units)->response()->getData(true),
            'Learning units retrieved successfully'
        );
    }

    public function store(StoreLearningUnitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $maxOrder = LearningUnit::where('learning_path_id', $data['learning_path_id'])->max('order');
        $data['order'] = $maxOrder !== null ? $maxOrder + 1 : 0;

        $unit = LearningUnit::query()->create($data);

        return $this->created(
            new LearningUnitResource($unit->load(['learningPath', 'lessons'])),
            'Learning unit created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $unit = LearningUnit::query()
            ->with(['learningPath', 'lessons'])
            ->where('id', $id)
            ->first();

        if (! $unit) {
            return $this->notFound('Learning unit not found');
        }

        return $this->success(
            new LearningUnitResource($unit),
            'Learning unit retrieved successfully'
        );
    }

    public function update(UpdateLearningUnitRequest $request, string $id): JsonResponse
    {
        $unit = LearningUnit::query()->where('id', $id)->first();

        if (! $unit) {
            return $this->notFound('Learning unit not found');
        }

        $unit->update($request->validated());

        return $this->success(
            new LearningUnitResource($unit->load(['learningPath', 'lessons'])),
            'Learning unit updated successfully'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = LearningUnit::query()->where('id', $id)->first();

        if (! $unit) {
            return $this->notFound('Learning unit not found');
        }

        $unit->delete();

        return $this->success(message: 'Learning unit deleted successfully');
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = LearningUnit::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Learning units permanently deleted';
        } else {
            $query->delete();
            $message = 'Learning units soft deleted';
        }

        return $this->success(message: $message);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $items = $request->items;

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                LearningUnit::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        return $this->success(message: 'Learning units reordered successfully');
    }
}
