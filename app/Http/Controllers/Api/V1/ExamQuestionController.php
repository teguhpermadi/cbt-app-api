<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ExamQuestion\BulkDeleteExamQuestionRequest;
use App\Http\Requests\Api\V1\ExamQuestion\BulkUpdateExamQuestionRequest;
use App\Http\Requests\Api\V1\ExamQuestion\StoreExamQuestionRequest;
use App\Http\Requests\Api\V1\ExamQuestion\UpdateExamQuestionRequest;
use App\Http\Resources\ExamQuestionResource;
use App\Models\ExamQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ExamQuestionController extends ApiController
{
    /**
     * Display a listing of exam questions with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $examQuestions = ExamQuestion::query()
            ->with(['exam', 'originalQuestion'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('question_text', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            ExamQuestionResource::collection($examQuestions)->response()->getData(true),
            'Exam questions retrieved successfully'
        );
    }

    /**
     * Store a newly created exam question in storage.
     */
    public function store(StoreExamQuestionRequest $request): JsonResponse
    {
        $examQuestion = ExamQuestion::query()->create($request->validated());

        return $this->created(
            new ExamQuestionResource($examQuestion->load(['exam', 'originalQuestion'])),
            'Exam question created successfully'
        );
    }

    /**
     * Display the specified exam question.
     */
    public function show(string $id): JsonResponse
    {
        $examQuestion = ExamQuestion::query()
            ->with(['exam', 'originalQuestion'])
            ->where('id', $id)
            ->first();

        if (! $examQuestion) {
            return $this->notFound('Exam question not found');
        }

        return $this->success(
            new ExamQuestionResource($examQuestion),
            'Exam question retrieved successfully'
        );
    }

    /**
     * Update the specified exam question in storage.
     */
    public function update(UpdateExamQuestionRequest $request, string $id): JsonResponse
    {
        $examQuestion = ExamQuestion::query()
            ->where('id', $id)
            ->first();

        if (! $examQuestion) {
            return $this->notFound('Exam question not found');
        }

        $examQuestion->update($request->validated());

        return $this->success(
            new ExamQuestionResource($examQuestion->load(['exam', 'originalQuestion'])),
            'Exam question updated successfully'
        );
    }

    /**
     * Remove the specified exam question from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $examQuestion = ExamQuestion::query()
            ->where('id', $id)
            ->first();

        if (! $examQuestion) {
            return $this->notFound('Exam question not found');
        }

        $examQuestion->delete();

        return $this->success(
            message: 'Exam question deleted successfully'
        );
    }

    /**
     * Bulk delete exam questions.
     */
    public function bulkDelete(BulkDeleteExamQuestionRequest $request): JsonResponse
    {
        $ids = $request->ids;

        ExamQuestion::whereIn('id', $ids)->delete();

        return $this->success(message: 'Exam questions deleted successfully');
    }

    /**
     * Bulk update exam questions.
     */
    public function bulkUpdate(BulkUpdateExamQuestionRequest $request): JsonResponse
    {
        $examQuestionsData = $request->exam_questions;

        DB::transaction(function () use ($examQuestionsData) {
            foreach ($examQuestionsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                ExamQuestion::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Exam questions updated successfully');
    }
}
