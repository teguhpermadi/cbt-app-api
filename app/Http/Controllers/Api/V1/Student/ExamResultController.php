<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\ExamResultResource;
use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExamResultController extends Controller
{
    /**
     * Get list of exam results for the current student.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $examResults = ExamResult::query()
            ->with([
                'exam' => function ($query) {
                    $query->with(['subject.classroom', 'academicYear']);
                }
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 10));

        return ExamResultResource::collection($examResults);
    }

    /**
     * Get leaderboard of exam results.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function leaderboard(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'exam_id' => 'nullable|exists:exams,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'classroom_id' => 'nullable|exists:classrooms,id',
        ]);

        $query = ExamResult::query()
            ->with([
                'user',
                'exam' => function ($query) {
                    $query->with(['subject.classroom', 'academicYear']);
                }
            ]);

        // Filter by Exam
        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->input('exam_id'));
        }

        // Filter by Subject
        if ($request->has('subject_id')) {
            $query->whereHas('exam', function ($q) use ($request) {
                $q->where('subject_id', $request->input('subject_id'));
            });
        }

        // Filter by Classroom
        if ($request->has('classroom_id')) {
            $query->whereHas('exam.subject', function ($q) use ($request) {
                $q->where('classroom_id', $request->input('classroom_id'));
            });
        }

        // Order by highest score
        $results = $query->orderByDesc('total_score')
            ->orderByDesc('score_percent')
            ->limit($request->input('limit', 10))
            ->get();

        return ExamResultResource::collection($results);
    }
}
