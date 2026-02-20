<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ReadingMaterial\BulkDeleteReadingMaterialRequest;
use App\Http\Requests\Api\V1\ReadingMaterial\StoreReadingMaterialRequest;
use App\Http\Requests\Api\V1\ReadingMaterial\UpdateReadingMaterialRequest;
use App\Http\Requests\Api\V1\ReadingMaterial\UploadMediaRequest;
use App\Http\Resources\ReadingMaterialResource;
use App\Models\ReadingMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ReadingMaterialController extends ApiController
{
    /**
     * Display a listing of reading materials with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $materials = ReadingMaterial::query()
            ->with(['user'])
            ->withCount('questions')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            ReadingMaterialResource::collection($materials)->response()->getData(true),
            'Reading materials retrieved successfully'
        );
    }

    /**
     * Store a newly created reading material in storage.
     */
    public function store(StoreReadingMaterialRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $material = ReadingMaterial::create($data);

        return $this->created(
            new ReadingMaterialResource($material->load('user')),
            'Reading material created successfully'
        );
    }

    /**
     * Display the specified reading material.
     */
    public function show(string $id): JsonResponse
    {
        $material = ReadingMaterial::query()
            ->with(['user', 'questions'])
            ->find($id);

        if (!$material) {
            return $this->notFound('Reading material not found');
        }

        return $this->success(
            new ReadingMaterialResource($material),
            'Reading material retrieved successfully'
        );
    }

    /**
     * Update the specified reading material in storage.
     */
    public function update(UpdateReadingMaterialRequest $request, string $id): JsonResponse
    {
        $material = ReadingMaterial::find($id);

        if (!$material) {
            return $this->notFound('Reading material not found');
        }

        $material->update($request->validated());

        return $this->success(
            new ReadingMaterialResource($material->load('user')),
            'Reading material updated successfully'
        );
    }

    /**
     * Remove the specified reading material from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $material = ReadingMaterial::find($id);

        if (!$material) {
            return $this->notFound('Reading material not found');
        }

        $material->delete();

        return $this->success(message: 'Reading material deleted successfully');
    }

    /**
     * Display a listing of trashed reading materials.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $materials = ReadingMaterial::onlyTrashed()
            ->with('user')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ReadingMaterialResource::collection($materials)->response()->getData(true),
            'Trashed reading materials retrieved successfully'
        );
    }

    /**
     * Restore a trashed reading material.
     */
    public function restore(string $id): JsonResponse
    {
        $material = ReadingMaterial::onlyTrashed()->find($id);

        if (!$material) {
            return $this->notFound('Trashed reading material not found');
        }

        $material->restore();

        return $this->success(
            new ReadingMaterialResource($material->load('user')),
            'Reading material restored successfully'
        );
    }

    /**
     * Permanently delete a reading material.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $material = ReadingMaterial::withTrashed()->find($id);

        if (!$material) {
            return $this->notFound('Reading material not found');
        }

        $material->forceDelete();

        return $this->success(message: 'Reading material permanently deleted');
    }

    /**
     * Bulk delete reading materials.
     */
    public function bulkDelete(BulkDeleteReadingMaterialRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = ReadingMaterial::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Reading materials permanently deleted';
        } else {
            $query->delete();
            $message = 'Reading materials soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Upload media to reading material.
     */
    public function uploadMedia(UploadMediaRequest $request, string $id): JsonResponse
    {
        $material = ReadingMaterial::findOrFail($id);
        $collection = $request->get('collection', 'reading_materials');

        $media = $material->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->uuid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media uploaded successfully');
    }

    /**
     * Replace media in reading material.
     */
    public function replaceMedia(UploadMediaRequest $request, string $id, string $mediaId): JsonResponse
    {
        $material = ReadingMaterial::findOrFail($id);
        $collection = $request->get('collection', 'reading_materials');

        $oldMedia = Media::where('model_id', $id)
            ->where('model_type', ReadingMaterial::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('uuid', $mediaId);
            })
            ->first();

        if ($oldMedia) {
            $oldMedia->delete();
        }

        $media = $material->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->uuid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media replaced successfully');
    }

    /**
     * Delete media from reading material.
     */
    public function deleteMedia(string $id, string $mediaId): JsonResponse
    {
        $media = Media::where('model_id', $id)
            ->where('model_type', ReadingMaterial::class)
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
}
