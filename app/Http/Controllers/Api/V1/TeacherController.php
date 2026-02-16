<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserTypeEnum;
use App\Http\Controllers\Api\ApiController;
use App\Exports\TeacherTemplateExport;
use App\Http\Requests\Api\V1\Teacher\BulkDeleteTeacherRequest;
use App\Http\Requests\Api\V1\Teacher\BulkUpdateTeacherRequest;
use App\Http\Requests\Api\V1\Teacher\ImportTeacherRequest;
use App\Http\Requests\Api\V1\Teacher\StoreTeacherRequest;
use App\Http\Requests\Api\V1\Teacher\UpdateTeacherRequest;
use App\Imports\TeachersImport;
use App\Http\Resources\TeacherResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

final class TeacherController extends ApiController
{
    /**
     * Display a listing of teachers with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $teachers = User::query()
            ->with('subjects')
            ->where('user_type', UserTypeEnum::TEACHER)
            ->latest()
            ->paginate($perPage);

        return $this->success(
            TeacherResource::collection($teachers)->response()->getData(true),
            'Teachers retrieved successfully'
        );
    }

    /**
     * Store a newly created teacher in storage.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = User::query()->create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => UserTypeEnum::TEACHER,
        ]);

        return $this->created(
            new TeacherResource($teacher),
            'Teacher created successfully'
        );
    }

    /**
     * Display the specified teacher.
     */
    public function show(string $id): JsonResponse
    {
        $teacher = User::query()
            ->with('subjects')
            ->where('user_type', UserTypeEnum::TEACHER)
            ->where('id', $id)
            ->first();

        if (! $teacher) {
            return $this->notFound('Teacher not found');
        }

        return $this->success(
            new TeacherResource($teacher),
            'Teacher retrieved successfully'
        );
    }

    /**
     * Update the specified teacher in storage.
     */
    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = User::query()
            ->with('subjects')
            ->where('user_type', UserTypeEnum::TEACHER)
            ->where('id', $id)
            ->first();

        if (! $teacher) {
            return $this->notFound('Teacher not found');
        }

        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $teacher->update($data);

        return $this->success(
            new TeacherResource($teacher),
            'Teacher updated successfully'
        );
    }

    /**
     * Remove the specified teacher from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $teacher = User::query()
            ->where('user_type', UserTypeEnum::TEACHER)
            ->where('id', $id)
            ->first();

        if (! $teacher) {
            return $this->notFound('Teacher not found');
        }

        $teacher->delete();

        return $this->success(
            message: 'Teacher deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted teachers.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $teachers = User::onlyTrashed()
            ->with('subjects')
            ->where('user_type', UserTypeEnum::TEACHER)
            ->latest()
            ->paginate($perPage);

        return $this->success(
            TeacherResource::collection($teachers)->response()->getData(true),
            'Trashed teachers retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted teacher.
     */
    public function restore(string $id): JsonResponse
    {
        $teacher = User::onlyTrashed()
            ->with('subjects')
            ->where('user_type', UserTypeEnum::TEACHER)
            ->where('id', $id)
            ->first();

        if (! $teacher) {
            return $this->notFound('Trashed teacher not found');
        }

        $teacher->restore();

        return $this->success(
            new TeacherResource($teacher),
            'Teacher restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted teacher.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $teacher = User::withTrashed()
            ->where('user_type', UserTypeEnum::TEACHER)
            ->where('id', $id)
            ->first();

        if (! $teacher) {
            return $this->notFound('Teacher not found');
        }

        $teacher->forceDelete();

        return $this->success(
            message: 'Teacher permanently deleted'
        );
    }

    /**
     * Bulk delete teachers.
     */
    public function bulkDelete(BulkDeleteTeacherRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = User::whereIn('id', $ids)->where('user_type', UserTypeEnum::TEACHER);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Teachers permanently deleted';
        } else {
            $query->delete();
            $message = 'Teachers soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update teachers.
     */
    public function bulkUpdate(BulkUpdateTeacherRequest $request): JsonResponse
    {
        $teachersData = $request->teachers;

        DB::transaction(function () use ($teachersData) {
            foreach ($teachersData as $data) {
                $id = $data['id'];
                unset($data['id']);

                if (isset($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                }

                User::where('id', $id)
                    ->where('user_type', UserTypeEnum::TEACHER)
                    ->update($data);
            }
        });

        return $this->success(message: 'Teachers updated successfully');
    }

    /**
     * Import teachers from Excel file.
     */
    public function import(ImportTeacherRequest $request): JsonResponse
    {
        try {
            Excel::import(new TeachersImport, $request->file('file'));
            return $this->success(message: 'Teachers imported successfully');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $messages = [];
            foreach ($failures as $failure) {
                $messages[] = 'Row ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }
            return response()->json([
                'success' => false,
                'message' => 'Import Validation Failed',
                'errors' => $messages,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import Failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download teacher import template.
     */
    public function template()
    {
        return Excel::download(new TeacherTemplateExport, 'teachers_template.xlsx');
    }
}
