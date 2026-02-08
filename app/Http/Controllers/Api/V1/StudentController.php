<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserTypeEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Student\BulkDeleteStudentRequest;
use App\Http\Requests\Api\V1\Student\BulkUpdateStudentRequest;
use App\Http\Requests\Api\V1\Student\StoreStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class StudentController extends ApiController
{
    /**
     * Display a listing of students with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $students = User::query()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->latest()
            ->paginate($perPage);

        return $this->success(
            StudentResource::collection($students)->response()->getData(true),
            'Students retrieved successfully'
        );
    }

    /**
     * Store a newly created student in storage.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = User::query()->create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => UserTypeEnum::STUDENT,
        ]);

        return $this->created(
            new StudentResource($student),
            'Student created successfully'
        );
    }

    /**
     * Display the specified student.
     */
    public function show(string $id): JsonResponse
    {
        $student = User::query()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->where('id', $id)
            ->first();

        if (! $student) {
            return $this->notFound('Student not found');
        }

        return $this->success(
            new StudentResource($student),
            'Student retrieved successfully'
        );
    }

    /**
     * Update the specified student in storage.
     */
    public function update(UpdateStudentRequest $request, string $id): JsonResponse
    {
        $student = User::query()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->where('id', $id)
            ->first();

        if (! $student) {
            return $this->notFound('Student not found');
        }

        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $student->update($data);

        return $this->success(
            new StudentResource($student),
            'Student updated successfully'
        );
    }

    /**
     * Remove the specified student from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $student = User::query()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->where('id', $id)
            ->first();

        if (! $student) {
            return $this->notFound('Student not found');
        }

        $student->delete();

        return $this->success(
            message: 'Student deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted students.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $students = User::onlyTrashed()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->latest()
            ->paginate($perPage);

        return $this->success(
            StudentResource::collection($students)->response()->getData(true),
            'Trashed students retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted student.
     */
    public function restore(string $id): JsonResponse
    {
        $student = User::onlyTrashed()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->where('id', $id)
            ->first();

        if (! $student) {
            return $this->notFound('Trashed student not found');
        }

        $student->restore();

        return $this->success(
            new StudentResource($student),
            'Student restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted student.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $student = User::withTrashed()
            ->where('user_type', UserTypeEnum::STUDENT)
            ->where('id', $id)
            ->first();

        if (! $student) {
            return $this->notFound('Student not found');
        }

        $student->forceDelete();

        return $this->success(
            message: 'Student permanently deleted'
        );
    }

    /**
     * Bulk delete students.
     */
    public function bulkDelete(BulkDeleteStudentRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = User::whereIn('id', $ids)->where('user_type', UserTypeEnum::STUDENT);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Students permanently deleted';
        } else {
            $query->delete();
            $message = 'Students soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update students.
     */
    public function bulkUpdate(BulkUpdateStudentRequest $request): JsonResponse
    {
        $studentsData = $request->students;

        DB::transaction(function () use ($studentsData) {
            foreach ($studentsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                if (isset($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                }

                User::where('id', $id)
                    ->where('user_type', UserTypeEnum::STUDENT)
                    ->update($data);
            }
        });

        return $this->success(message: 'Students updated successfully');
    }
}
