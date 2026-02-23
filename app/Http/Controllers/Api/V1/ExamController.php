<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Exam\BulkDeleteExamRequest;
use App\Http\Requests\Api\V1\Exam\BulkUpdateExamRequest;
use App\Http\Requests\Api\V1\Exam\StoreExamRequest;
use App\Http\Requests\Api\V1\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ExamController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');
        $academicYearId = $request->input('academic_year_id');

        $exams = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->when($academicYearId, fn($query) => $query->where('academic_year_id', $academicYearId))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('subject', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            ExamResource::collection($exams)->response()->getData(true),
            'Exams retrieved successfully'
        );
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate random 6-digit token if not provided or if user wants it auto-generated
        // Namun, jika user mengirim token kosong atau null, kita generate.
        if (empty($data['token'])) {
            // Generate 6 digit random number
            $data['token'] = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $classroomIds = $data['classroom_ids'] ?? [];
        unset($data['classroom_ids']);

        $exam = DB::transaction(function () use ($data, $classroomIds) {
            $exam = Exam::query()->create($data);
            $exam->classrooms()->sync($classroomIds);

            // Snapshot questions from QuestionBank
            if ($exam->question_bank_id) {
                $bank = \App\Models\QuestionBank::find($exam->question_bank_id);
                if ($bank) {
                    $questions = $bank->questions;
                    $order = 1;
                    foreach ($questions as $question) {
                        \App\Models\ExamQuestion::create([
                            'exam_id' => $exam->id,
                            'question_id' => $question->id,
                            'question_number' => $order++,
                            'content' => $question->content,
                            'options' => $question->getOptionsForExam(),
                            'key_answer' => $question->getKeyAnswerForExam(),
                            'score_value' => $question->score?->value ?? 1,
                            'question_type' => $question->type,
                            'difficulty_level' => $question->difficulty,
                            'media_path' => $question->getFirstMediaUrl('question_content'),
                            'hint' => $question->hint,
                        ]);
                    }
                }
            }

            return $exam;
        });

        return $this->created(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user', 'classrooms'])),
            'Exam created successfully'
        );
    }

    /**
     * Display the specified exam.
     */
    public function show(string $id): JsonResponse
    {
        $exam = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user', 'classrooms'])
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        return $this->success(
            new ExamResource($exam),
            'Exam retrieved successfully'
        );
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(UpdateExamRequest $request, string $id): JsonResponse
    {
        $exam = Exam::query()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $data = $request->validated();
        $classroomIds = $data['classroom_ids'] ?? null;
        unset($data['classroom_ids']);

        DB::transaction(function () use ($exam, $data, $classroomIds) {
            $exam->update($data);

            if ($classroomIds !== null) {
                $exam->classrooms()->sync($classroomIds);
            }
        });

        return $this->success(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user', 'classrooms'])),
            'Exam updated successfully'
        );
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::query()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $exam->delete();

        return $this->success(
            message: 'Exam deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted exams.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $exams = Exam::onlyTrashed()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ExamResource::collection($exams)->response()->getData(true),
            'Trashed exams retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted exam.
     */
    public function restore(string $id): JsonResponse
    {
        $exam = Exam::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Trashed exam not found');
        }

        $exam->restore();

        return $this->success(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
            'Exam restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted exam.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $exam = Exam::withTrashed()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found');
        }

        $exam->forceDelete();

        return $this->success(
            message: 'Exam permanently deleted'
        );
    }

    /**
     * Bulk delete exams.
     */
    public function bulkDelete(BulkDeleteExamRequest $request): JsonResponse
    {
        $ids = $request->ids;
        $force = $request->boolean('force');

        $query = Exam::whereIn('id', $ids);

        if ($force) {
            $query->withTrashed()->forceDelete();
            $message = 'Exams permanently deleted';
        } else {
            $query->delete();
            $message = 'Exams soft-deleted';
        }

        return $this->success(message: $message);
    }

    /**
     * Bulk update exams.
     */
    public function bulkUpdate(BulkUpdateExamRequest $request): JsonResponse
    {
        $examsData = $request->exams;

        DB::transaction(function () use ($examsData) {
            foreach ($examsData as $data) {
                $id = $data['id'];
                unset($data['id']);

                Exam::where('id', $id)->update($data);
            }
        });

        return $this->success(message: 'Exams updated successfully');
    }
    /**
     * Get live score and student progress for an exam.
     */
    public function liveScore(Request $request, Exam $exam): JsonResponse
    {
        // Load classrooms and their students
        $exam->load(['classrooms.students', 'subject']);

        $classrooms = $exam->classrooms;

        if ($classrooms->isEmpty()) {
            return $this->error('No classrooms assigned to this exam', 404);
        }

        $students = $classrooms->flatMap->students->unique('id');

        // 2. Ambil sesi ujian yang aktif/selesai untuk ujian ini
        $examSessions = ExamSession::where('exam_id', $exam->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->withCount([
                'examResultDetails as total_questions',
                'examResultDetails as answered_count' => function ($query) {
                    $query->whereNotNull('student_answer');
                }
            ])
            ->withSum('examResultDetails as current_score', 'score_earned')
            ->get()
            ->keyBy('user_id');

        // 3. Format data response
        $data = $students->map(function ($student) use ($examSessions, $exam, $classrooms) {
            $session = $examSessions->get($student->id);

            $status = 'idle'; // 'not_started' mapped to 'idle'
            $startTime = null;
            $remainingTime = 0;
            $currentScore = 0;
            $extraTime = 0;
            $progress = [
                'answered' => 0,
                'total' => 0,
            ];

            if ($session) {
                // Detect logical statuses
                $isTimedOut = $this->calculateRemainingTime($session, $exam) <= 0;
                $isAllAnswered = ($session->total_questions > 0) && ($session->answered_count >= $session->total_questions);

                if ($session->is_finished) {
                    $status = 'finished';
                } elseif ($isTimedOut) {
                    $status = 'timed_out';
                } elseif ($isAllAnswered) {
                    $status = 'completed';
                } else {
                    $status = 'in_progress';
                }

                $startTime = $session->start_time;
                $currentScore = $session->current_score ?? 0;
                $extraTime = $session->extra_time ?? 0;

                $progress = [
                    'answered' => (int) $session->answered_count,
                    'total' => (int) $session->total_questions,
                ];

                if ($status === 'in_progress' || $status === 'completed' || $status === 'timed_out') {
                    $remainingTime = $this->calculateRemainingTime($session, $exam);
                }

                if ($session->is_finished) {
                    $currentScore = $session->total_score;
                }
            }

            return [
                'id' => $student->id, // Using student ID as session ID for unique keys
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'avatar' => $student->avatar,
                    'classroom' => $classrooms->first(fn($c) => $c->students->contains('id', $student->id))?->name ?? 'N/A',
                ],
                'status' => $status,
                'start_time' => $startTime,
                'remaining_time' => $remainingTime,
                'score' => (float) $currentScore, // mapped from current_score/total_score
                'extra_time' => $extraTime,
                'progress' => $progress,
            ];
        });

        return $this->success([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'duration' => $exam->duration,
                'token' => $exam->token,
                'classrooms' => $classrooms->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
                'subject' => [
                    'name' => $exam->subject->name,
                ],
                'academic_year' => [
                    'year' => $exam->academicYear?->year,
                ],
            ],
            'sessions' => $data->values(),
        ]);
    }

    /**
     * Reset exam for a specific student.
     */
    public function resetExam(Request $request, Exam $exam): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $userId = $request->user_id;

        DB::transaction(function () use ($exam, $userId) {
            // Hapus ExamSession
            ExamSession::where('exam_id', $exam->id)
                ->where('user_id', $userId)
                ->forceDelete(); // Gunakan forceDelete agar benar-benar bersih

            // Hapus ExamResult
            ExamResult::where('exam_id', $exam->id)
                ->where('user_id', $userId)
                ->forceDelete();

            // Opsional: Hapus ExamAnswer/ExamResultDetail jika ada tabel terpisah yang menyimpan jawaban per soal
            // ExamResultDetail::whereHas('examSession', function($q) use ($exam, $userId) { ... })->delete();
        });

        return $this->success(message: 'Exam reset successfully');
    }

    /**
     * Add extra time for a specific student.
     */
    public function addTime(Request $request, Exam $exam): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        $session = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $request->user_id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('Active exam session not found for this student', 404);
        }

        $session->increment('extra_time', $request->minutes);

        return $this->success(message: 'Extra time added successfully');
    }

    /**
     * Force finish exam for a specific student.
     */
    public function forceFinish(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
        ]);

        $session = ExamSession::where('exam_id', $id)
            ->where('user_id', $request->user_id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('No active session found for this student.', 404);
        }

        DB::transaction(function () use ($session) {
            $session->update([
                'is_finished' => true,
                'finish_time' => now(),
            ]);

            // Dispatch Scoring Job (Calculate final score asynchronously)
            \App\Jobs\CalculateExamScoreJob::dispatch($session);
        });

        return $this->success(message: 'Exam force finished successfully');
    }

    /**
     * Reopen a finished exam session.
     */
    public function reopen(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'minutes' => 'nullable|integer|min:0',
        ]);

        $session = ExamSession::where('exam_id', $id)
            ->where('user_id', $request->user_id)
            ->where('is_finished', true)
            ->first();

        if (! $session) {
            return $this->error('No finished session found for this student.', 404);
        }

        $session->update([
            'is_finished' => false,
            'finish_time' => null,
            'extra_time' => $session->extra_time + ($request->minutes ?? 0),
        ]);

        return $this->success(message: 'Exam session reopened successfully.');
    }
    /**
     * Regenerate exam token.
     */
    public function regenerateToken(Exam $exam): JsonResponse
    {
        $newToken = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $exam->update(['token' => $newToken]);

        return $this->success(
            ['token' => $newToken],
            'Token regenerated successfully'
        );
    }


    /**
     * Calculate remaining time for a session.
     */
    private function calculateRemainingTime(ExamSession $session, Exam $exam): int
    {
        $startTime = Carbon::parse($session->start_time);
        $duration = $exam->duration + ($session->extra_time ?? 0);

        // Hitung waktu selesai berdasarkan durasi
        $endTimeByDuration = $startTime->copy()->addMinutes($duration);

        // Hitung waktu selesai berdasarkan batas akhir ujian (jika ada)
        $endTimeBySchedule = $exam->end_time ? Carbon::parse($exam->end_time) : null;

        // Ambil waktu selesai paling awal (minimum)
        $endTime = $endTimeBySchedule
            ? $endTimeByDuration->min($endTimeBySchedule)
            : $endTimeByDuration;

        $now = now();

        if ($now->greaterThanOrEqualTo($endTime)) {
            return 0;
        }

        return $now->diffInSeconds($endTime);
    }
}
