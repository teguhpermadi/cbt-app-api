<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

final class TagController extends ApiController
{
    /**
     * Display a listing of tags.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim();
        $limit = $request->integer('limit', 20);

        $query = Tag::query()
            ->when($search, function ($q) use ($search) {
                // Spatie tags store translations in a JSON column natively, but checking the whole JSON or a specific locale is common
                // The most basic approach for exact/like match when name is localized (Spatie HasTags default):
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit($limit);

        $tags = $query->get()->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        });

        return $this->success(
            $tags,
            'Tags retrieved successfully'
        );
    }
}
