<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Exam\BulkDeleteExamRequest;
use App\Http\Requests\Api\V1\Exam\BulkUpdateExamRequest;
use App\Http\Requests\Api\V1\Exam\StoreExamRequest;
use App\Http\Requests\Api\V1\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ExamController extends ApiController
{
    /**
     * Display a listing of exams with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $exams = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ExamResource::collection($exams)->response()->getData(true),
            'Exams retrieved successfully'
        );
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = Exam::query()->create($request->validated());

        return $this->created(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
            'Exam created successfully'
        );
    }

    /**
     * Display the specified exam.
     */
    public function show(string $id): JsonResponse
    {
        $exam = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        return $this->success(
            new ExamResource($exam),
            'Exam retrieved successfully'
        );
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(UpdateExamRequest $request, string $id): JsonResponse
    {
        $exam = Exam::query()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $exam->update($request->validated());

        return $this->success(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
            'Exam updated successfully'
        );
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::query()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $exam->delete();

        return $this->success(
            message: 'Exam deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted exams.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $exams = Exam::onlyTrashed()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ExamResource::collection($exams)->response()->getData(true),
            'Trashed exams retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted exam.
     */
    public function restore(string $id): JsonResponse
    {
        $exam = Exam::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Trashed exam not found');
        }

        $exam->restore();

        return $this->success(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
            'Exam restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted exam.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $exam = Exam::withTrashed()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $exam->forceDelete();

        return $this->success(
            message: 'Exam permanently deleted'
        );
    }

    /**
     * Bulk delete exams.
     */
    public function bulkDelete(BulkDeleteExamRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = Exam::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Exams permanently deleted';
        } else {
            $query->delete();
            $message = 'Exams soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update exams.
     */
    public function bulkUpdate(BulkUpdateExamRequest $request): JsonResponse
    {
        $examsData = $request->exams;

        DB::transaction(function () use ($examsData) {
            foreach ($examsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                Exam::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Exams updated successfully');
    }
}
