<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Question\BulkDeleteQuestionRequest;
use App\Http\Requests\Api\V1\Question\BulkUpdateQuestionRequest;
use App\Http\Requests\Api\V1\Question\StoreQuestionRequest;
use App\Http\Requests\Api\V1\Question\UpdateQuestionRequest;
use App\Http\Requests\Api\V1\Question\UploadMediaRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class QuestionController extends ApiController
{
    /**
     * Display a listing of questions with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $questions = Question::query()
            ->with(['user', 'readingMaterial', 'tags'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('question_text', 'like', "%{$search}%")
                        ->orWhere('explanation', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            QuestionResource::collection($questions)->response()->getData(true),
            'Questions retrieved successfully'
        );
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $data['user_id'] = \Illuminate\Support\Facades\Auth::id();

        $question = Question::create($data);

        if (!empty($tags)) {
            $question->attachTags($tags);
        }

        return $this->created(
            new QuestionResource($question->load(['user', 'readingMaterial', 'tags'])),
            'Question created successfully'
        );
    }

    /**
     * Display the specified question.
     */
    public function show(string $id): JsonResponse
    {
        $question = Question::query()
            ->with(['user', 'readingMaterial', 'tags', 'options'])
            ->find($id);

        if (!$question) {
            return $this->notFound('Question not found');
        }

        return $this->success(
            new QuestionResource($question),
            'Question retrieved successfully'
        );
    }

    /**
     * Update the specified question in storage.
     */
    public function update(UpdateQuestionRequest $request, string $id): JsonResponse
    {
        $question = Question::query()->find($id);

        if (!$question) {
            return $this->notFound('Question not found');
        }

        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $question->update($data);

        if ($tags !== null) {
            $question->syncTags($tags);
        }

        return $this->success(
            new QuestionResource($question->load(['user', 'readingMaterial', 'tags'])),
            'Question updated successfully'
        );
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $question = Question::query()->find($id);

        if (!$question) {
            return $this->notFound('Question not found');
        }

        $question->delete();

        return $this->success(
            message: 'Question deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted questions.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $questions = Question::onlyTrashed()
            ->with(['user', 'readingMaterial', 'tags'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            QuestionResource::collection($questions)->response()->getData(true),
            'Trashed questions retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted question.
     */
    public function restore(string $id): JsonResponse
    {
        $question = Question::onlyTrashed()
            ->with(['user', 'readingMaterial', 'tags'])
            ->find($id);

        if (!$question) {
            return $this->notFound('Trashed question not found');
        }

        $question->restore();

        return $this->success(
            new QuestionResource($question),
            'Question restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted question.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $question = Question::withTrashed()
            ->find($id);

        if (!$question) {
            return $this->notFound('Question not found');
        }

        $question->forceDelete();

        return $this->success(
            message: 'Question permanently deleted'
        );
    }

    /**
     * Bulk delete questions.
     */
    public function bulkDelete(BulkDeleteQuestionRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = Question::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Questions permanently deleted';
        } else {
            $query->delete();
            $message = 'Questions soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update questions.
     */
    public function bulkUpdate(BulkUpdateQuestionRequest $request): JsonResponse
    {
        $questionsData = $request->questions;

        DB::transaction(function () use ($questionsData) {
            foreach ($questionsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                Question::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Questions updated successfully');
    }

    /**
     * Upload media to a question.
     */
    public function uploadMedia(UploadMediaRequest $request, string $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        $collection = $request->get('collection', 'question_content');

        $media = $question->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->ulid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media uploaded successfully');
    }

    /**
     * Replace media in a question.
     */
    public function replaceMedia(UploadMediaRequest $request, string $id, string $mediaId): JsonResponse
    {
        $question = Question::findOrFail($id);
        $collection = $request->get('collection', 'question_content');

        // Delete old media
        $oldMedia = Media::where('model_id', $id)
            ->where('model_type', Question::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('ulid', $mediaId);
            })
            ->first();

        if ($oldMedia) {
            $oldMedia->delete();
        }

        // Add new media
        $media = $question->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->ulid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media replaced successfully');
    }

    /**
     * Delete media from a question.
     */
    public function deleteMedia(string $id, string $mediaId): JsonResponse
    {
        $media = Media::where('model_id', $id)
            ->where('model_type', Question::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('ulid', $mediaId);
            })
            ->first();

        if (!$media) {
            return $this->notFound('Media not found');
        }

        $media->delete();

        return $this->success(message: 'Media deleted successfully');
    }
}
