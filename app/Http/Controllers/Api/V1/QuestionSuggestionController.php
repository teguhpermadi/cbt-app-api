<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuestionSuggestionStateEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\QuestionSuggestion\StoreQuestionSuggestionRequest;
use App\Http\Requests\Api\V1\QuestionSuggestion\UpdateQuestionSuggestionRequest;
use App\Http\Resources\QuestionSuggestionResource;
use App\Models\Question;
use App\Models\QuestionSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionSuggestionController extends ApiController
{
    /**
     * Display a listing of the resource with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $state = $request->input('state');
        $questionId = $request->input('question_id');
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $query = QuestionSuggestion::query()
            ->with(['user', 'question']);

        if ($state) {
            $query->where('state', $state);
        }

        if ($questionId) {
            $query->where('question_id', $questionId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('suggestion_text', 'like', "%{$search}%")
                    ->orWhere('new_text', 'like', "%{$search}%");
            });
        }

        $suggestions = $query->orderBy($sortBy, $order)->paginate($perPage);

        return $this->success(
            QuestionSuggestionResource::collection($suggestions)->response()->getData(true),
            'Question suggestions retrieved successfully'
        );
    }

    /**
     * Display a listing of the user's suggestions.
     */
    public function mine(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $suggestions = QuestionSuggestion::query()
            ->where('user_id', Auth::id())
            ->with(['question'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            QuestionSuggestionResource::collection($suggestions)->response()->getData(true),
            'My suggestions retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuestionSuggestionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['state'] = QuestionSuggestionStateEnum::PENDING;

        $suggestion = QuestionSuggestion::create($data);

        return $this->created(
            new QuestionSuggestionResource($suggestion),
            'Question suggestion created successfully'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $suggestion = QuestionSuggestion::query()
            ->with(['user', 'question'])
            ->find($id);

        if (!$suggestion) {
            return $this->notFound('Question suggestion not found');
        }

        return $this->success(
            new QuestionSuggestionResource($suggestion),
            'Question suggestion retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuestionSuggestionRequest $request, string $id): JsonResponse
    {
        $suggestion = QuestionSuggestion::find($id);

        if (!$suggestion) {
            return $this->notFound('Question suggestion not found');
        }

        if ($suggestion->user_id !== Auth::id()) {
            return $this->unauthorized('You are not authorized to update this suggestion');
        }

        if ($suggestion->state !== QuestionSuggestionStateEnum::PENDING) {
            return $this->error('Only pending suggestions can be updated');
        }

        $suggestion->update($request->validated());

        return $this->success(
            new QuestionSuggestionResource($suggestion),
            'Question suggestion updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $suggestion = QuestionSuggestion::find($id);

        if (!$suggestion) {
            return $this->notFound('Question suggestion not found');
        }

        if ($suggestion->user_id !== Auth::id() && !Auth::user()->isAdmin()) { // Assuming isAdmin helper exists or similar logic
            // Or check if user is admin via roles/permissions. For now, let's assume policy or simple check.
            // If strictly only owner can delete:
            return $this->unauthorized('You are not authorized to delete this suggestion');
        }

        $suggestion->delete();

        return $this->success(message: 'Question suggestion deleted successfully');
    }

    /**
     * Approve the suggestion.
     */
    public function approve(string $id): JsonResponse
    {
        $suggestion = QuestionSuggestion::find($id);

        if (!$suggestion) {
            return $this->notFound('Question suggestion not found');
        }

        if ($suggestion->question->user_id !== Auth::id()) {
            return $this->unauthorized('You are not authorized to approve this suggestion');
        }

        if ($suggestion->state !== QuestionSuggestionStateEnum::PENDING) {
            return $this->error('Only pending suggestions can be approved');
        }

        // Apply changes to the question
        $question = $suggestion->question;
        if ($suggestion->data) {
            $question->update($suggestion->data);
        }

        $suggestion->update(['state' => QuestionSuggestionStateEnum::APPROVED]);

        return $this->success(
            new QuestionSuggestionResource($suggestion),
            'Question suggestion approved successfully'
        );
    }

    /**
     * Reject the suggestion.
     */
    public function reject(string $id): JsonResponse
    {
        $suggestion = QuestionSuggestion::find($id);

        if (!$suggestion) {
            return $this->notFound('Question suggestion not found');
        }

        if ($suggestion->question->user_id !== Auth::id()) {
            return $this->unauthorized('You are not authorized to reject this suggestion');
        }

        if ($suggestion->state !== QuestionSuggestionStateEnum::PENDING) {
            return $this->error('Only pending suggestions can be rejected');
        }

        $suggestion->update(['state' => QuestionSuggestionStateEnum::REJECTED]);

        return $this->success(
            new QuestionSuggestionResource($suggestion),
            'Question suggestion rejected successfully'
        );
    }
}
