<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\QuestionBank\StoreQuestionBankRequest;
use App\Http\Requests\Api\V1\QuestionBank\UpdateQuestionBankRequest;
use App\Http\Resources\QuestionBankResource;
use App\Models\QuestionBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class QuestionBankController extends ApiController
{
    /**
     * Display a listing of question banks.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $questionBanks = QuestionBank::query()
            ->with(['user', 'subject'])
            ->withCount('questions')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            QuestionBankResource::collection($questionBanks)->response()->getData(true),
            'Question banks retrieved successfully'
        );
    }

    /**
     * Store a newly created question bank in storage.
     */
    public function store(StoreQuestionBankRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $questionBank = QuestionBank::create($data);

        return $this->created(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank created successfully'
        );
    }

    /**
     * Display the specified question bank.
     */
    public function show(string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()
            ->with(['user', 'subject', 'questions' => function ($query) {
                $query->with(['tags', 'options']);
                $query->orderBy('order', 'asc');
            }])
            ->withCount('questions')
            ->find($id);

        if (!$questionBank) {
            return $this->notFound('Question bank not found');
        }

        return $this->success(
            new QuestionBankResource($questionBank),
            'Question bank retrieved successfully'
        );
    }

    /**
     * Update the specified question bank in storage.
     */
    public function update(UpdateQuestionBankRequest $request, string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()->find($id);

        if (!$questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->update($request->validated());

        return $this->success(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank updated successfully'
        );
    }

    /**
     * Remove the specified question bank from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()->find($id);

        if (!$questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->delete();

        return $this->success(
            message: 'Question bank deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted question banks.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $questionBanks = QuestionBank::onlyTrashed()
            ->with(['user', 'subject'])
            ->withCount('questions')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            QuestionBankResource::collection($questionBanks)->response()->getData(true),
            'Trashed question banks retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted question bank.
     */
    public function restore(string $id): JsonResponse
    {
        $questionBank = QuestionBank::onlyTrashed()
            ->find($id);

        if (!$questionBank) {
            return $this->notFound('Trashed question bank not found');
        }

        $questionBank->restore();

        return $this->success(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted question bank.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $questionBank = QuestionBank::withTrashed()
            ->find($id);

        if (!$questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->forceDelete();

        return $this->success(
            message: 'Question bank permanently deleted'
        );
    }

    /**
     * Import questions from Word document.
     */
    public function import(
        \App\Http\Requests\Api\V1\QuestionBank\ImportQuestionRequest $request,
        string $id,
        \App\Services\QuestionImportService $importService
    ): JsonResponse {
        $questionBank = QuestionBank::find($id);

        if (!$questionBank) {
            return $this->notFound('Question bank not found');
        }

        $file = $request->file('file');
        $path = $file->path(); // Get temporary path

        try {
            $result = $importService->parseWordDocument(
                filePath: $path,
                questionBankId: $questionBank->id,
                authorId: Auth::id()
            );

            if ($result['success']) {
                return $this->success(
                    $result,
                    "Berhasil mengimport {$result['total']} soal."
                );
            }

            return $this->error(
                'Gagal mengimport soal.',
                422,
                $result['errors']
            );
        } catch (\Exception $e) {
            return $this->error(
                'Terjadi kesalahan saat memproses file.',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }
}
