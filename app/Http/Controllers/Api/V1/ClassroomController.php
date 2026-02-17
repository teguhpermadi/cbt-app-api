<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Classroom\BulkDeleteClassroomRequest;
use App\Http\Requests\Api\V1\Classroom\BulkUpdateClassroomRequest;
use App\Http\Requests\Api\V1\Classroom\StoreClassroomRequest;
use App\Http\Requests\Api\V1\Classroom\SyncStudentsRequest;
use App\Http\Requests\Api\V1\Classroom\UpdateClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClassroomController extends ApiController
{
    /**
     * Display a listing of classrooms with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $classrooms = Classroom::query()
            ->with(['user', 'academicYear'])
            ->withCount('students')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            ClassroomResource::collection($classrooms)->response()->getData(true),
            'Classrooms retrieved successfully'
        );
    }

    /**
     * Store a newly created classroom in storage.
     */
    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $classroom = Classroom::query()->create($request->validated());

        return $this->created(
            new ClassroomResource($classroom->load(['user', 'academicYear'])),
            'Classroom created successfully'
        );
    }

    /**
     * Display the specified classroom.
     */
    public function show(string $id): JsonResponse
    {
        $classroom = Classroom::query()
            ->with(['user', 'academicYear', 'students'])
            ->withCount('students')
            ->where('id', $id)
            ->first();

        if (! $classroom) {
            return $this->notFound('Classroom not found');
        }

        return $this->success(
            new ClassroomResource($classroom),
            'Classroom retrieved successfully'
        );
    }

    /**
     * Update the specified classroom in storage.
     */
    public function update(UpdateClassroomRequest $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()
            ->where('id', $id)
            ->first();

        if (! $classroom) {
            return $this->notFound('Classroom not found');
        }

        $classroom->update($request->validated());

        return $this->success(
            new ClassroomResource($classroom->load(['user', 'academicYear'])),
            'Classroom updated successfully'
        );
    }

    /**
     * Remove the specified classroom from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $classroom = Classroom::query()
            ->where('id', $id)
            ->first();

        if (! $classroom) {
            return $this->notFound('Classroom not found');
        }

        $classroom->delete();

        return $this->success(
            message: 'Classroom deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted classrooms.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $classrooms = Classroom::onlyTrashed()
            ->with(['user', 'academicYear'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ClassroomResource::collection($classrooms)->response()->getData(true),
            'Trashed classrooms retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted classroom.
     */
    public function restore(string $id): JsonResponse
    {
        $classroom = Classroom::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (! $classroom) {
            return $this->notFound('Trashed classroom not found');
        }

        $classroom->restore();

        return $this->success(
            new ClassroomResource($classroom->load(['user', 'academicYear'])),
            'Classroom restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted classroom.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $classroom = Classroom::withTrashed()
            ->where('id', $id)
            ->first();

        if (! $classroom) {
            return $this->notFound('Classroom not found');
        }

        $classroom->forceDelete();

        return $this->success(
            message: 'Classroom permanently deleted'
        );
    }

    /**
     * Bulk delete classrooms.
     */
    public function bulkDelete(BulkDeleteClassroomRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = Classroom::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Classrooms permanently deleted';
        } else {
            $query->delete();
            $message = 'Classrooms soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update classrooms.
     */
    public function bulkUpdate(BulkUpdateClassroomRequest $request): JsonResponse
    {
        $classroomsData = $request->classrooms;

        DB::transaction(function () use ($classroomsData) {
            foreach ($classroomsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                Classroom::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Classrooms updated successfully');
    }

    /**
     * Display a listing of the authenticated user's classrooms.
     */
    public function mine(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $academicYearId = $request->input('academic_year_id');

        if (! $academicYearId) {
            $latestAcademicYear = AcademicYear::latest()->first();
            $academicYearId = $latestAcademicYear?->id;
        }

        $classrooms = Classroom::query()
            ->mine()
            ->when($academicYearId, fn($query) => $query->where('academic_year_id', $academicYearId))
            ->with(['user', 'academicYear'])
            ->withCount('students')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ClassroomResource::collection($classrooms)->response()->getData(true),
            'My classrooms retrieved successfully'
        );
    }

    /**
     * Synchronize students for a specific academic year.
     */
    public function syncStudents(SyncStudentsRequest $request, Classroom $classroom): JsonResponse
    {
        $classroom->syncStudents($request->student_ids, $request->academic_year_id);

        return $this->success(
            message: 'Students synchronized successfully'
        );
    }
}
