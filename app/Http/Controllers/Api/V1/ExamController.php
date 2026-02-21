<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Exam\BulkDeleteExamRequest;
use App\Http\Requests\Api\V1\Exam\BulkUpdateExamRequest;
use App\Http\Requests\Api\V1\Exam\StoreExamRequest;
use App\Http\Requests\Api\V1\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
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
    /**
     * Display a listing of exams with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $exams = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
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

        $exam = Exam::query()->create($data);

        return $this->created(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
            'Exam created successfully'
        );
    }

    /**
     * Display the specified exam.
     */
    public function show(string $id): JsonResponse
    {
        $exam = Exam::query()
            ->with(['academicYear', 'subject', 'questionBank', 'user'])
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

        $exam->update($request->validated());

        return $this->success(
            new ExamResource($exam->load(['academicYear', 'subject', 'questionBank', 'user'])),
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
        // Exam belongs to a Subject, which belongs to a Classroom.
        // So an Exam is specific to one Classroom.
        $exam->load(['subject.classroom.students']);

        $classroom = $exam->subject->classroom;

        if (! $classroom) {
            return $this->error('Classroom not found for this exam', 404);
        }

        $students = $classroom->students;

        // 2. Ambil sesi ujian yang aktif/selesai untuk ujian ini
        $examSessions = ExamSession::where('exam_id', $exam->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->keyBy('user_id');

        // 3. Format data response
        $data = $students->map(function ($student) use ($examSessions, $exam) {
            $session = $examSessions->get($student->id);

            $status = 'not_started';
            $startTime = null;
            $remainingTime = null;
            $currentScore = 0;
            $extraTime = 0;

            if ($session) {
                $status = $session->is_finished ? 'done' : 'doing';
                $startTime = $session->start_time;
                $currentScore = $session->current_score ?? 0; // Asumsi ada field atau hitungan score sementara
                $extraTime = $session->extra_time ?? 0;

                if ($status === 'doing') {
                    $remainingTime = $this->calculateRemainingTime($session, $exam);
                }

                // Jika session finished, gunakan nilai final/total score
                if ($session->is_finished) {
                    $currentScore = $session->total_score;
                }
            }

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'avatar' => $student->avatar, // Jika ada
                'status' => $status,
                'start_time' => $startTime,
                'remaining_time' => $remainingTime,
                'current_score' => $currentScore,
                'extra_time' => $extraTime,
            ];
        });

        return $this->success([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'duration' => $exam->duration,
                'classroom' => $classroom->name,
            ],
            'students' => $data->values(),
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
    public function forceFinish(Request $request, Exam $exam): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $session = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $request->user_id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('Active exam session not found for this student', 404);
        }

        // Logic untuk menyelesaikan ujian
        // Ini mungkin perlu memanggil service atau logic yang sama dengan ketika siswa klik "Selesai"
        // Untuk penyederhanaan di sini, kita set status manual dan mungkin perlu trigger calculation score

        DB::transaction(function () use ($session) {
            $session->update([
                'is_finished' => true,
                'finish_time' => now(),
            ]);

            // TODO: Trigger score calculation here if logic is separate
            // $examService->calculateScore($session);
        });

        return $this->success(message: 'Exam force finished successfully');
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
