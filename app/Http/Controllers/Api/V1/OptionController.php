<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Option\StoreOptionRequest;
use App\Http\Requests\Api\V1\Option\UpdateOptionRequest;
use App\Http\Requests\Api\V1\Question\UploadMediaRequest;
use App\Http\Resources\OptionResource;
use App\Models\Option;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class OptionController extends ApiController
{
    /**
     * Display a listing of options with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $questionId = $request->get('question_id');
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $query = Option::query()->with(['question']);

        if ($questionId) {
            $query->where('question_id', $questionId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('option_text', 'like', "%{$search}%");
            });
        }

        $options = $query->orderBy($sortBy, $order)->paginate($perPage);

        return $this->success(
            OptionResource::collection($options)->response()->getData(true),
            'Options retrieved successfully'
        );
    }

    /**
     * Store a newly created option in storage.
     */
    public function store(StoreOptionRequest $request): JsonResponse
    {
        $option = Option::create($request->validated());

        return $this->created(
            new OptionResource($option->load(['question'])),
            'Option created successfully'
        );
    }

    /**
     * Display the specified option.
     */
    public function show(string $id): JsonResponse
    {
        $option = Option::query()->with(['question'])->find($id);

        if (!$option) {
            return $this->notFound('Option not found');
        }

        return $this->success(
            new OptionResource($option),
            'Option retrieved successfully'
        );
    }

    /**
     * Update the specified option in storage.
     */
    public function update(UpdateOptionRequest $request, string $id): JsonResponse
    {
        $option = Option::query()->find($id);

        if (!$option) {
            return $this->notFound('Option not found');
        }

        $option->update($request->validated());

        return $this->success(
            new OptionResource($option->load(['question'])),
            'Option updated successfully'
        );
    }

    /**
     * Remove the specified option from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $option = Option::query()->find($id);

        if (!$option) {
            return $this->notFound('Option not found');
        }

        $option->delete();

        return $this->success(
            message: 'Option deleted successfully'
        );
    }

    /**
     * Upload media to an option.
     */
    public function uploadMedia(UploadMediaRequest $request, string $id): JsonResponse
    {
        $option = Option::findOrFail($id);
        $collection = $request->get('collection', 'option_media');

        $media = $option->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->ulid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media uploaded successfully');
    }

    /**
     * Replace media in an option.
     */
    public function replaceMedia(UploadMediaRequest $request, string $id, string $mediaId): JsonResponse
    {
        $option = Option::findOrFail($id);
        $collection = $request->get('collection', 'option_media');

        // Delete old media
        $oldMedia = Media::where('model_id', $id)
            ->where('model_type', Option::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('ulid', $mediaId);
            })
            ->first();

        if ($oldMedia) {
            $oldMedia->delete();
        }

        // Add new media
        $media = $option->addMediaFromRequest('media')
            ->toMediaCollection($collection);

        return $this->success([
            'id' => $media->ulid ?? $media->id,
            'url' => $media->getFullUrl(),
            'name' => $media->name,
        ], 'Media replaced successfully');
    }

    /**
     * Delete media from an option.
     */
    public function deleteMedia(string $id, string $mediaId): JsonResponse
    {
        $media = Media::where('model_id', $id)
            ->where('model_type', Option::class)
            ->where(function ($query) use ($mediaId) {
                $query->where('id', $mediaId)->orWhere('ulid', $mediaId);
            })
            ->first();

        if (!$media) {
            return $this->notFound('Media not found');
        }

        $media->delete();

        return $this->success(message: 'Media deleted successfully');
    }
}
