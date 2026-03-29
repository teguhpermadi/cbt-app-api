<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Learning\BulkDeleteRequest;
use App\Http\Requests\Learning\ReorderRequest;
use App\Http\Requests\Learning\StoreLearningLessonRequest;
use App\Http\Requests\Learning\UpdateLearningLessonRequest;
use App\Http\Resources\Learning\LearningLessonResource;
use App\Models\LearningLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LearningLessonController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $learningUnitId = $request->input('learning_unit_id');
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'order');
        $order = $request->string('order', 'asc');

        $isPublished = $request->boolean('is_published');

        $lessons = LearningLesson::query()
            ->with(['unit', 'questionBank'])
            ->when($learningUnitId, fn ($q) => $q->where('learning_unit_id', $learningUnitId))
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($isPublished !== null, fn ($q) => $q->where('is_published', $isPublished))
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            LearningLessonResource::collection($lessons)->response()->getData(true),
            'Learning lessons retrieved successfully'
        );
    }

    public function store(StoreLearningLessonRequest $request): JsonResponse
    {
        $data = $request->validated();
        $maxOrder = LearningLesson::where('learning_unit_id', $data['learning_unit_id'])->max('order');
        $data['order'] = $maxOrder !== null ? $maxOrder + 1 : 0;

        $lesson = LearningLesson::query()->create($data);

        return $this->created(
            new LearningLessonResource($lesson->load(['unit', 'questionBank'])),
            'Learning lesson created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $lesson = LearningLesson::query()
            ->with(['unit', 'questionBank'])
            ->where('id', $id)
            ->first();

        if (! $lesson) {
            return $this->notFound('Learning lesson not found');
        }

        return $this->success(
            new LearningLessonResource($lesson),
            'Learning lesson retrieved successfully'
        );
    }

    public function update(UpdateLearningLessonRequest $request, string $id): JsonResponse
    {
        $lesson = LearningLesson::query()->where('id', $id)->first();

        if (! $lesson) {
            return $this->notFound('Learning lesson not found');
        }

        $lesson->update($request->validated());

        return $this->success(
            new LearningLessonResource($lesson->load(['unit', 'questionBank'])),
            'Learning lesson updated successfully'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $lesson = LearningLesson::query()->where('id', $id)->first();

        if (! $lesson) {
            return $this->notFound('Learning lesson not found');
        }

        $lesson->delete();

        return $this->success(message: 'Learning lesson deleted successfully');
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = LearningLesson::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Learning lessons permanently deleted';
        } else {
            $query->delete();
            $message = 'Learning lessons soft deleted';
        }

        return $this->success(message: $message);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $items = $request->items;

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                LearningLesson::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        return $this->success(message: 'Learning lessons reordered successfully');
    }

    public function uploadMedia(UploadMediaRequest $request, string $id): JsonResponse
    {
        $lesson = LearningLesson::findOrFail($id);
        $collection = $request->get('collection', 'reading_files');

        $media = $lesson->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->uuid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ], 'Media uploaded successfully');
    }

    public function replaceMedia(UploadMediaRequest $request, string $id, string $mediaId): JsonResponse
    {
        $lesson = LearningLesson::findOrFail($id);
        $collection = $request->get('collection', 'reading_files');

        $oldMedia = Media::where('model_id', $id)
            ->where('model_type', LearningLesson::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('uuid', $mediaId);
            })
            ->first();

        if ($oldMedia) {
            $oldMedia->delete();
        }

        $media = $lesson->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->uuid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ], 'Media replaced successfully');
    }

    public function deleteMedia(string $id, string $mediaId): JsonResponse
    {
        $lesson = LearningLesson::findOrFail($id);

        $media = Media::where('model_id', $id)
            ->where('model_type', LearningLesson::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('uuid', $mediaId);
            })
            ->first();

        if (! $media) {
            return $this->notFound('Media not found');
        }

        $media->delete();

        return $this->success(message: 'Media deleted successfully');
    }
}
