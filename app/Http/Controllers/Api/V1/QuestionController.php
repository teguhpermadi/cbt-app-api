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
                    $q->where('content', 'like', "%{$search}%")
                        ->orWhere('hint', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('question_bank_id'), function ($query) use ($request) {
                $query->whereHas('questionBanks', function ($q) use ($request) {
                    $q->where('question_bank_id', $request->question_bank_id);
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
        $questionBankId = $data['question_bank_id'] ?? null;

        // Extract option-related data
        $optionsData = $data['options'] ?? [];
        $matchingPairs = $data['matching_pairs'] ?? [];
        $sequenceItems = $data['sequence_items'] ?? [];
        $keywords = $data['keywords'] ?? '';

        // Clean up data for Question model
        unset($data['tags']);
        unset($data['question_bank_id']);
        unset($data['options']);
        unset($data['matching_pairs']);
        unset($data['sequence_items']);
        unset($data['keywords']);

        $data['user_id'] = \Illuminate\Support\Facades\Auth::id();

        // Auto-calculate order if not provided or to ensure it's last
        if ($questionBankId) {
            $maxOrder = Question::whereHas('questionBanks', function ($q) use ($questionBankId) {
                $q->where('question_bank_id', $questionBankId);
            })->max('order');

            $data['order'] = $maxOrder ? $maxOrder + 1 : 1;
        } else {
            // Fallback if no bank context, default to 1 or existing logic
            $data['order'] = $data['order'] ?? 1;
        }

        $question = DB::transaction(function () use ($data, $tags, $questionBankId, $optionsData, $matchingPairs, $sequenceItems, $keywords) {
            $question = Question::create($data);

            if (!empty($tags)) {
                $question->attachTags($tags);
            }

            if ($questionBankId) {
                $question->questionBanks()->attach($questionBankId);
            }

            $this->saveOptions($question, $optionsData, $matchingPairs, $sequenceItems, $keywords);

            return $question;
        });

        return $this->created(
            new QuestionResource($question->load(['user', 'readingMaterial', 'tags', 'options'])),
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

        // Extract option-related data
        $optionsData = $data['options'] ?? [];
        $matchingPairs = $data['matching_pairs'] ?? [];
        $sequenceItems = $data['sequence_items'] ?? [];
        $keywords = $data['keywords'] ?? '';

        unset($data['tags']);
        unset($data['options']);
        unset($data['matching_pairs']);
        unset($data['sequence_items']);
        unset($data['keywords']);

        $question = DB::transaction(function () use ($question, $data, $tags, $optionsData, $matchingPairs, $sequenceItems, $keywords) {
            $question->update($data);

            if ($tags !== null) {
                $question->syncTags($tags);
            }

            // Re-create options
            // Hard delete old options or soft delete? 
            // Since we are replacing structure, might be cleaner to soft-delete all and create new.
            // But if we want to keep IDs for some reason? 
            // In the plan, we decided to delete and recreate.
            $question->options()->delete(); // Soft delete

            $this->saveOptions($question, $optionsData, $matchingPairs, $sequenceItems, $keywords);

            return $question;
        });

        return $this->success(
            new QuestionResource($question->load(['user', 'readingMaterial', 'tags', 'options'])),
            'Question updated successfully'
        );
    }

    /**
     * Helper to save options based on question type
     */
    private function saveOptions(Question $question, array $optionsData, array $matchingPairs, array $sequenceItems, string $keywords): void
    {
        switch ($question->type) {
            case \App\Enums\QuestionTypeEnum::MULTIPLE_CHOICE:
            case \App\Enums\QuestionTypeEnum::MULTIPLE_SELECTION:
                \App\Models\Option::createMultipleChoiceOptions($question->id, $optionsData);
                break;

            case \App\Enums\QuestionTypeEnum::TRUE_FALSE:
                // For True/False, we receive options with is_correct flag.
                // Helper createTrueFalseOptions expects a boolean for correct answer.
                // We check which option is marked correct.
                $correctOption = collect($optionsData)->firstWhere('is_correct', true);
                if ($correctOption) {
                    // Assuming content is "True" or "False", or just checking logical true
                    // The helper takes `bool $correctAnswer`. 
                    // If the user selected "True" as correct, we pass true.
                    // If "False" is correct, we pass false.
                    // We need to know which one is the "True" option.
                    // Standard: A=True, B=False usually.
                    // Or we just check: if content=True/Benar and is_correct=true -> true.

                    // Actually, the helper creates generic True/False options. 
                    // Let's look at `createTrueFalseOptions` implementation again.
                    // It creates 2 options: T (Correct if $correct=true) and F (Correct if $correct=false).
                    // So we just need to pass true/false.

                    $isTrueCorrect = false;
                    if (strtolower($correctOption['content']) === 'true' || strtolower($correctOption['content']) === 'benar') {
                        $isTrueCorrect = true;
                    }
                    // What if the user changed the text? 
                    // Maybe better to rely on Key if standard?
                    // Let's rely on the fact that if the FIRST option (usually T) is correct, then true.
                    // Or simply: check if the option with content 'True'/'Benar' is correct.

                    // Allow simple override: if $optionsData has content, maybe we should use `createMultipleChoiceOptions` instead?
                    // If we use T/F helper, it hardcodes content to 'Benar'/'Salah'. 
                    // If frontend sends 'True'/'False', we might want that.

                    // Let's stick to using the helper for consistency if that's what it entails.
                    // But if frontend sends customized text, better use general create.
                    // Given `CreateQuestionPage` sets T=True, F=False.

                    // Let's use generic creation to support custom text if needed, 
                    // but `createTrueFalseOptions` is handy. 
                    // Let's use `createMultipleChoiceOptions` because it is flexible enough for T/F too if we pass them as standard options!
                    // T/F is just a 2-option MC.
                    \App\Models\Option::createMultipleChoiceOptions($question->id, $optionsData);
                }
                break;

            case \App\Enums\QuestionTypeEnum::MATCHING:
                \App\Models\Option::createMatchingOptions($question->id, $matchingPairs);
                break;

            case \App\Enums\QuestionTypeEnum::SEQUENCE:
                // Extract content list for helper
                $items = collect($sequenceItems)->pluck('content')->toArray();
                \App\Models\Option::createOrderingOptions($question->id, $items);
                break;

            case \App\Enums\QuestionTypeEnum::SHORT_ANSWER:
                \App\Models\Option::createShortAnswerOptions($question->id, $optionsData);
                break;

            case \App\Enums\QuestionTypeEnum::ESSAY:
                // Essay might accept a rubric or keywords
                \App\Models\Option::createEssayOption($question->id, $keywords);
                break;

                // Add other types if needed (Math, Strings, etc.)
        }
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
