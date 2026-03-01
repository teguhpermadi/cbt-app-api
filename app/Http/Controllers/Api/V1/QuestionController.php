<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Question\BulkDeleteQuestionRequest;
use App\Http\Requests\Api\V1\Question\BulkUpdateQuestionRequest;
use App\Http\Requests\Api\V1\Question\ImportWordRequest;
use App\Http\Requests\Api\V1\Question\StoreQuestionRequest;
use App\Http\Requests\Api\V1\Question\UpdateQuestionRequest;
use App\Http\Requests\Api\V1\Question\UploadMediaRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Services\WordToDatabaseParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $mathContent = $data['math_content'] ?? '';
        $arabicContent = $data['arabic_content'] ?? '';
        $javaneseContent = $data['javanese_content'] ?? '';
        $categorizationGroups = $data['categorization_groups'] ?? [];


        // Clean up data for Question model
        unset($data['tags']);
        unset($data['question_bank_id']);
        unset($data['options']);
        unset($data['matching_pairs']);
        unset($data['sequence_items']);
        unset($data['keywords']);
        unset($data['math_content']);
        unset($data['arabic_content']);
        unset($data['javanese_content']);
        unset($data['categorization_groups']);


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

        $question = DB::transaction(function () use ($request, $data, $tags, $questionBankId, $optionsData, $matchingPairs, $sequenceItems, $keywords, $mathContent, $arabicContent, $javaneseContent, $categorizationGroups) {

            $question = Question::create($data);

            if (!empty($tags)) {
                $question->attachTags($tags);
            }

            if ($questionBankId) {
                $question->questionBanks()->attach($questionBankId);
            }

            // Handle Question Image
            if ($request->hasFile('question_image')) {
                $question->addMediaFromRequest('question_image')->toMediaCollection('question_content');
            }

            $this->saveOptions($question, $optionsData, $matchingPairs, $sequenceItems, $keywords, $mathContent, $arabicContent, $javaneseContent, $categorizationGroups);


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
        $mathContent = $data['math_content'] ?? '';
        $arabicContent = $data['arabic_content'] ?? '';
        $javaneseContent = $data['javanese_content'] ?? '';
        $categorizationGroups = $data['categorization_groups'] ?? [];


        unset($data['tags']);
        unset($data['options']);
        unset($data['matching_pairs']);
        unset($data['sequence_items']);
        unset($data['keywords']);
        unset($data['math_content']);
        unset($data['arabic_content']);
        unset($data['javanese_content']);
        unset($data['categorization_groups']);


        $question = DB::transaction(function () use ($request, $question, $data, $tags, $optionsData, $matchingPairs, $sequenceItems, $keywords, $mathContent, $arabicContent, $javaneseContent, $categorizationGroups) {

            $question->update($data);

            if ($tags !== null) {
                $question->syncTags($tags);
            }

            // Handle Question Image
            if ($request->hasFile('question_image')) {
                $question->addMediaFromRequest('question_image')->toMediaCollection('question_content');
            }

            // Sync options instead of delete/re-create
            // Only sync options if they are explicitly provided in the request or if the question type is changing
            if ($request->hasAny(['type', 'options', 'matching_pairs', 'sequence_items', 'keywords', 'math_content', 'arabic_content', 'javanese_content', 'categorization_groups'])) {
                $this->saveOptions($question, $optionsData, $matchingPairs, $sequenceItems, $keywords, $mathContent, $arabicContent, $javaneseContent, $categorizationGroups);
            }

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
    private function saveOptions(Question $question, array $optionsData, array $matchingPairs, array $sequenceItems, string $keywords, string $mathContent, string $arabicContent, string $javaneseContent, array $categorizationGroups): void

    {
        switch ($question->type) {
            case \App\Enums\QuestionTypeEnum::MULTIPLE_CHOICE:
            case \App\Enums\QuestionTypeEnum::MULTIPLE_SELECTION:
                $existingIds = collect($optionsData)->pluck('id')->filter()->toArray();

                // Delete options not in the new list
                $question->options()->whereNotIn('id', $existingIds)->delete();

                foreach ($optionsData as $index => $data) {
                    $optionData = [
                        'question_id' => $question->id,
                        'option_key' => $data['option_key'] ?? $data['key'] ?? chr(65 + $index),
                        'content' => $data['content'] ?? '',
                        'order' => $data['order'] ?? $index,
                        'is_correct' => $data['is_correct'] ?? false,
                    ];

                    if (!empty($data['id'])) {
                        $option = \App\Models\Option::find($data['id']);
                        if ($option) {
                            $option->update($optionData);
                        }
                    } else {
                        $option = \App\Models\Option::create($optionData);
                    }

                    // Handle Option Images
                    if ($option && isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $option->addMedia($data['image'])->toMediaCollection('option_media');
                    }
                }
                break;

            case \App\Enums\QuestionTypeEnum::TRUE_FALSE:
                // For True/False, we still re-create or sync simply as they don't have media yet
                $question->options()->delete();
                \App\Models\Option::createMultipleChoiceOptions($question->id, $optionsData);
                break;

            case \App\Enums\QuestionTypeEnum::MATCHING:
                $question->options()->delete();
                \App\Models\Option::createMatchingOptions($question->id, $matchingPairs);
                break;

            case \App\Enums\QuestionTypeEnum::SEQUENCE:
                $question->options()->delete();
                $items = collect($sequenceItems)->pluck('content')->toArray();
                \App\Models\Option::createOrderingOptions($question->id, $items);
                break;

            case \App\Enums\QuestionTypeEnum::SHORT_ANSWER:
                $question->options()->delete();
                \App\Models\Option::createShortAnswerOptions($question->id, $optionsData);
                break;

            case \App\Enums\QuestionTypeEnum::ESSAY:
                $question->options()->delete();
                \App\Models\Option::createEssayOption($question->id, $keywords);
                break;

            case \App\Enums\QuestionTypeEnum::MATH_INPUT:
                $question->options()->delete();
                \App\Models\Option::createMathInputOption($question->id, $mathContent);
                break;

            case \App\Enums\QuestionTypeEnum::ARABIC_RESPONSE:
                $question->options()->delete();
                \App\Models\Option::createArabicOption($question->id, $arabicContent);
                break;
            case \App\Enums\QuestionTypeEnum::JAVANESE_RESPONSE:
                $question->options()->delete();
                \App\Models\Option::createJavaneseOption($question->id, $javaneseContent);
                break;


            case \App\Enums\QuestionTypeEnum::CATEGORIZATION:
                $question->options()->delete();
                $createdOptions = \App\Models\Option::createCategorizationOptions($question->id, $categorizationGroups);

                // Handle images for categorization items
                // The structure of categorizationGroups is expected to match what Option::createCategorizationOptions expects
                // but we need to match the created options back to the input to find images.
                $optionIndex = 0;
                foreach ($categorizationGroups as $groupIndex => $group) {
                    $items = $group['items'] ?? [];
                    foreach ($items as $itemIndex => $item) {
                        $option = $createdOptions[$optionIndex] ?? null;
                        if ($option && isset($item['image']) && $item['image'] instanceof \Illuminate\Http\UploadedFile) {
                            $option->addMedia($item['image'])->toMediaCollection('option_media');
                        }
                        $optionIndex++;
                    }
                }
                break;
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
            'id' => $media->uuid ?? $media->id,
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
                $query->where('id', $mediaId)->orWhere('uuid', $mediaId);
            })
            ->first();

        if ($oldMedia) {
            $oldMedia->delete();
        }

        // Add new media
        $media = $question->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->uuid ?? $media->id,
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
                $query->where('id', $mediaId)->orWhere('uuid', $mediaId);
            })
            ->first();

        if (!$media) {
            return $this->notFound('Media not found');
        }

        $media->delete();

        return $this->success(message: 'Media deleted successfully');
    }

    /**
     * Import questions from a Word document (2-column Key-Value format).
     */
    public function importWord(ImportWordRequest $request, WordToDatabaseParserService $service): JsonResponse
    {
        $file = $request->file('file');
        $questionBankId = $request->get('question_bank_id');
        $authorId = \Illuminate\Support\Facades\Auth::id();

        $result = $service->parse($file->getRealPath(), $authorId, $questionBankId);

        if (!$result['success']) {
            return $this->error('Word import failed', 422, $result['errors']);
        }

        return $this->success(
            [
                'total_imported' => $result['total'],
                'errors' => $result['errors']
            ],
            'Questions imported successfully'
        );
    }

    /**
     * Download the Word template for question import.
     */
    public function downloadTemplate(WordToDatabaseParserService $service): BinaryFileResponse
    {
        $filePath = $service->generateTemplate();

        return response()->download($filePath, 'template_soal_word.docx')->deleteFileAfterSend(true);
    }
}
