<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuestionBankReviewStateEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\QuestionBankReviewer\StoreQuestionBankReviewerRequest;
use App\Http\Requests\Api\V1\QuestionBankReviewer\UpdateQuestionBankReviewerRequest;
use App\Http\Resources\QuestionBankReviewerResource;
use App\Models\QuestionBankReviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class QuestionBankReviewerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');
        $questionBankId = $request->input('question_bank_id');
        $state = $request->input('state');

        $query = QuestionBankReviewer::query()
            ->forUser()
            ->with(['user', 'questionBank' => function ($q) {
                $q->with(['user', 'subject']);
            }]);

        if ($questionBankId) {
            $query->where('question_bank_id', $questionBankId);
        }

        if ($state) {
            $query->where('state', $state);
        }

        if ($search) {
            $query->whereHas('questionBank', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('academic_year_id')) {
            $query->whereHas('questionBank.subject', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('questionBank', function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        $reviewers = $query->orderBy($sortBy, $order)->paginate($perPage);

        return $this->success(
            QuestionBankReviewerResource::collection($reviewers)->response()->getData(true),
            'Question bank reviewers retrieved successfully'
        );
    }

    public function mine(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');
        $questionBankId = $request->input('question_bank_id');
        $state = $request->input('state');

        $query = QuestionBankReviewer::query()
            ->mine()
            ->with(['user', 'questionBank' => function ($q) {
                $q->with(['user', 'subject']);
            }]);

        if ($questionBankId) {
            $query->where('question_bank_id', $questionBankId);
        }

        if ($state) {
            $query->where('state', $state);
        }

        if ($search) {
            $query->whereHas('questionBank', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $reviewers = $query->orderBy($sortBy, $order)->paginate($perPage);

        return $this->success(
            QuestionBankReviewerResource::collection($reviewers)->response()->getData(true),
            'My question bank reviewers retrieved successfully'
        );
    }

    public function public(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');
        $questionBankId = $request->input('question_bank_id');
        $state = $request->input('state');

        $query = QuestionBankReviewer::query()
            ->public()
            ->with(['user', 'questionBank' => function ($q) {
                $q->with(['user', 'subject']);
            }]);

        if ($questionBankId) {
            $query->where('question_bank_id', $questionBankId);
        }

        if ($state) {
            $query->where('state', $state);
        }

        if ($search) {
            $query->whereHas('questionBank', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('questionBank', function ($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        if ($request->filled('user_id')) {
            $query->whereHas('questionBank', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        $reviewers = $query->orderBy($sortBy, $order)->paginate($perPage);

        return $this->success(
            QuestionBankReviewerResource::collection($reviewers)->response()->getData(true),
            'Public question bank reviewers retrieved successfully'
        );
    }

    public function store(StoreQuestionBankReviewerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['state'] = QuestionBankReviewStateEnum::PENDING;

        $reviewer = QuestionBankReviewer::create($data);

        return $this->created(
            new QuestionBankReviewerResource($reviewer->load(['user', 'questionBank'])),
            'Question bank reviewer created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $reviewer = QuestionBankReviewer::query()
            ->with(['user', 'questionBank'])
            ->find($id);

        if (! $reviewer) {
            return $this->notFound('Question bank reviewer not found');
        }

        return $this->success(
            new QuestionBankReviewerResource($reviewer),
            'Question bank reviewer retrieved successfully'
        );
    }

    public function update(UpdateQuestionBankReviewerRequest $request, string $id): JsonResponse
    {
        $reviewer = QuestionBankReviewer::find($id);

        if (! $reviewer) {
            return $this->notFound('Question bank reviewer not found');
        }

        $user = Auth::user();
        $questionBank = $reviewer->questionBank;

        if ($reviewer->user_id !== $user->id && $questionBank->user_id !== $user->id) {
            return $this->unauthorized('You are not authorized to update this reviewer');
        }

        if ($reviewer->state !== QuestionBankReviewStateEnum::PENDING) {
            return $this->error('Only pending reviewers can be updated');
        }

        $reviewer->update($request->validated());

        return $this->success(
            new QuestionBankReviewerResource($reviewer->load(['user', 'questionBank'])),
            'Question bank reviewer updated successfully'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $reviewer = QuestionBankReviewer::find($id);

        if (! $reviewer) {
            return $this->notFound('Question bank reviewer not found');
        }

        $user = Auth::user();
        $questionBank = $reviewer->questionBank;

        if ($reviewer->user_id !== $user->id && $questionBank->user_id !== $user->id) {
            return $this->unauthorized('You are not authorized to delete this reviewer');
        }

        $reviewer->delete();

        return $this->success(message: 'Question bank reviewer deleted successfully');
    }

    public function approve(string $id): JsonResponse
    {
        $reviewer = QuestionBankReviewer::with('questionBank')->find($id);

        if (! $reviewer) {
            return $this->notFound('Question bank reviewer not found');
        }

        $questionBank = $reviewer->questionBank;

        if ($questionBank->user_id !== Auth::id()) {
            return $this->unauthorized('You are not authorized to approve this reviewer');
        }

        if ($reviewer->state !== QuestionBankReviewStateEnum::PENDING) {
            return $this->error('Only pending reviewers can be approved');
        }

        $reviewer->update(['state' => QuestionBankReviewStateEnum::APPROVED]);

        return $this->success(
            new QuestionBankReviewerResource($reviewer->load(['user', 'questionBank'])),
            'Question bank reviewer approved successfully'
        );
    }

    public function reject(string $id): JsonResponse
    {
        $reviewer = QuestionBankReviewer::with('questionBank')->find($id);

        if (! $reviewer) {
            return $this->notFound('Question bank reviewer not found');
        }

        $questionBank = $reviewer->questionBank;

        if ($questionBank->user_id !== Auth::id()) {
            return $this->unauthorized('You are not authorized to reject this reviewer');
        }

        if ($reviewer->state !== QuestionBankReviewStateEnum::PENDING) {
            return $this->error('Only pending reviewers can be rejected');
        }

        $reviewer->update(['state' => QuestionBankReviewStateEnum::REJECTED]);

        return $this->success(
            new QuestionBankReviewerResource($reviewer->load(['user', 'questionBank'])),
            'Question bank reviewer rejected successfully'
        );
    }
}
