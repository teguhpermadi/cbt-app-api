<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\AcademicYear\BulkDeleteAcademicYearRequest;
use App\Http\Requests\Api\V1\AcademicYear\BulkUpdateAcademicYearRequest;
use App\Http\Requests\Api\V1\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\Api\V1\AcademicYear\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AcademicYearController extends ApiController
{
    /**
     * Display a listing of academic years with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $academicYears = AcademicYear::query()
            ->with(['user'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('year', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            AcademicYearResource::collection($academicYears)->response()->getData(true),
            'Academic years retrieved successfully'
        );
    }

    /**
     * Store a newly created academic year in storage.
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $academicYear = AcademicYear::query()->create($request->validated());

        return $this->created(
            new AcademicYearResource($academicYear->load(['user'])),
            'Academic year created successfully'
        );
    }

    /**
     * Display the specified academic year.
     */
    public function show(string $id): JsonResponse
    {
        $academicYear = AcademicYear::query()
            ->with(['user'])
            ->where('id', $id)
            ->first();

        if (! $academicYear) {
            return $this->notFound('Academic year not found');
        }

        return $this->success(
            new AcademicYearResource($academicYear),
            'Academic year retrieved successfully'
        );
    }

    /**
     * Update the specified academic year in storage.
     */
    public function update(UpdateAcademicYearRequest $request, string $id): JsonResponse
    {
        $academicYear = AcademicYear::query()
            ->where('id', $id)
            ->first();

        if (! $academicYear) {
            return $this->notFound('Academic year not found');
        }

        $academicYear->update($request->validated());

        return $this->success(
            new AcademicYearResource($academicYear->load(['user'])),
            'Academic year updated successfully'
        );
    }

    /**
     * Remove the specified academic year from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $academicYear = AcademicYear::query()
            ->where('id', $id)
            ->first();

        if (! $academicYear) {
            return $this->notFound('Academic year not found');
        }

        $academicYear->delete();

        return $this->success(
            message: 'Academic year deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted academic years.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $academicYears = AcademicYear::onlyTrashed()
            ->with(['user'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            AcademicYearResource::collection($academicYears)->response()->getData(true),
            'Trashed academic years retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted academic year.
     */
    public function restore(string $id): JsonResponse
    {
        $academicYear = AcademicYear::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (! $academicYear) {
            return $this->notFound('Trashed academic year not found');
        }

        $academicYear->restore();

        return $this->success(
            new AcademicYearResource($academicYear->load(['user'])),
            'Academic year restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted academic year.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $academicYear = AcademicYear::withTrashed()
            ->where('id', $id)
            ->first();

        if (! $academicYear) {
            return $this->notFound('Academic year not found');
        }

        $academicYear->forceDelete();

        return $this->success(
            message: 'Academic year permanently deleted'
        );
    }

    /**
     * Bulk delete academic years.
     */
    public function bulkDelete(BulkDeleteAcademicYearRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = AcademicYear::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Academic years permanently deleted';
        } else {
            $query->delete();
            $message = 'Academic years soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update academic years.
     */
    public function bulkUpdate(BulkUpdateAcademicYearRequest $request): JsonResponse
    {
        $academicYearsData = $request->academic_years;

        DB::transaction(function () use ($academicYearsData) {
            foreach ($academicYearsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                AcademicYear::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Academic years updated successfully');
    }
}
