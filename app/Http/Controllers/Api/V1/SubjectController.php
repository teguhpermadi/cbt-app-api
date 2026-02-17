<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Subject\BulkDeleteSubjectRequest;
use App\Http\Requests\Api\V1\Subject\BulkUpdateSubjectRequest;
use App\Http\Requests\Api\V1\Subject\StoreSubjectRequest;
use App\Http\Requests\Api\V1\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubjectController extends ApiController
{
    /**
     * Display a listing of subjects with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $subjects = Subject::query()
            ->with(['user', 'academicYear', 'classroom'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('class_name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            SubjectResource::collection($subjects)->response()->getData(true),
            'Subjects retrieved successfully'
        );
    }

    /**
     * Store a newly created subject in storage.
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::query()->create($request->validated());

        return $this->created(
            new SubjectResource($subject->load(['user', 'academicYear', 'classroom'])),
            'Subject created successfully'
        );
    }

    /**
     * Display the specified subject.
     */
    public function show(string $id): JsonResponse
    {
        $subject = Subject::query()
            ->with(['user', 'academicYear', 'classroom'])
            ->where('id', $id)
            ->first();

        if (! $subject) {
            return $this->notFound('Subject not found');
        }

        return $this->success(
            new SubjectResource($subject),
            'Subject retrieved successfully'
        );
    }

    /**
     * Update the specified subject in storage.
     */
    public function update(UpdateSubjectRequest $request, string $id): JsonResponse
    {
        $subject = Subject::query()
            ->where('id', $id)
            ->first();

        if (! $subject) {
            return $this->notFound('Subject not found');
        }

        $subject->update($request->validated());

        return $this->success(
            new SubjectResource($subject->load(['user', 'academicYear', 'classroom'])),
            'Subject updated successfully'
        );
    }

    /**
     * Remove the specified subject from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $subject = Subject::query()
            ->where('id', $id)
            ->first();

        if (! $subject) {
            return $this->notFound('Subject not found');
        }

        $subject->delete();

        return $this->success(
            message: 'Subject deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted subjects.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $subjects = Subject::onlyTrashed()
            ->with(['user', 'academicYear', 'classroom'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            SubjectResource::collection($subjects)->response()->getData(true),
            'Trashed subjects retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted subject.
     */
    public function restore(string $id): JsonResponse
    {
        $subject = Subject::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (! $subject) {
            return $this->notFound('Trashed subject not found');
        }

        $subject->restore();

        return $this->success(
            new SubjectResource($subject->load(['user', 'academicYear', 'classroom'])),
            'Subject restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted subject.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $subject = Subject::withTrashed()
            ->where('id', $id)
            ->first();

        if (! $subject) {
            return $this->notFound('Subject not found');
        }

        $subject->forceDelete();

        return $this->success(
            message: 'Subject permanently deleted'
        );
    }

    /**
     * Bulk delete subjects.
     */
    public function bulkDelete(BulkDeleteSubjectRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = Subject::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Subjects permanently deleted';
        } else {
            $query->delete();
            $message = 'Subjects soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update subjects.
     */
    public function bulkUpdate(BulkUpdateSubjectRequest $request): JsonResponse
    {
        $subjectsData = $request->subjects;

        DB::transaction(function () use ($subjectsData) {
            foreach ($subjectsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                Subject::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Subjects updated successfully');
    }

    /**
     * Display a listing of the authenticated user's subjects.
     */
    public function mine(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $academicYearId = $request->input('academic_year_id');

        if (! $academicYearId) {
            $latestAcademicYear = AcademicYear::latest()->first();
            $academicYearId = $latestAcademicYear?->id;
        }

        $subjects = Subject::query()
            ->where('user_id', auth()->id())
            ->when($academicYearId, fn($query) => $query->where('academic_year_id', $academicYearId))
            ->with(['user', 'academicYear', 'classroom'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            SubjectResource::collection($subjects)->response()->getData(true),
            'My subjects retrieved successfully'
        );
    }
}
