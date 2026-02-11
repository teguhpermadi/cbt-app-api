<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ExamResource;
use App\Http\Resources\Student\ExamResultDetailResource;
use App\Http\Resources\Student\ExamSessionResource;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamController extends ApiController
{
    /**
     * List available exams for the student.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->integer('per_page', 15);

        // Filter exams based on student's classrooms -> subjects
        // A student belongs to classrooms. Classrooms have subjects. Exams belong to subjects.
        // So we get exams where the exam's subject's classroom has the student.

        // Actually, looking at the models:
        // Classroom -> subjects (HasMany)
        // Classroom -> students (BelongsToMany)
        // Exam -> subject (BelongsTo)
        // So: Exam -> Subject -> Classroom -> Has Student (User)

        $exams = Exam::query()
            ->where('is_published', true)
            ->whereHas('subject.classroom.students', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            // Optional: Filter by start_time / end_time validity
            // ->where(function ($q) {
            //     $now = now();
            //     $q->whereNull('start_time')->orWhere('start_time', '<=', $now);
            // })
            // ->where(function ($q) {
            //     $now = now();
            //     $q->whereNull('end_time')->orWhere('end_time', '>=', $now);
            // })
            ->with(['subject', 'academicYear', 'questionBank'])
            ->latest()
            ->paginate($perPage);

        // Process exams to add status (e.g., if already taken, attempts left)
        // This might be better done in a Resource or Service, but for now we rely on the client to check 
        // We can append 'current_session' or 'attempts_count' if needed.
        // Let's add `attempts_count` and `latest_session_status` via subqueries or separate loading if performance allows.

        // checking sessions and results
        $exams->getCollection()->transform(function ($exam) use ($user) {
            $sessions = ExamSession::where('exam_id', $exam->id)
                ->where('user_id', $user->id)
                ->get();

            $exam->attempts_count = $sessions->count();
            $exam->latest_session = $sessions->last();

            $exam->best_result = \App\Models\ExamResult::where('exam_id', $exam->id)
                ->where('user_id', $user->id)
                ->first();

            return $exam;
        });

        return $this->success(
            ExamResource::collection($exams)->response()->getData(true),
            'Available exams retrieved successfully'
        );
    }

    /**
     * Show exam details.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        $exam = Exam::query()
            ->where('is_published', true)
            ->where('id', $id)
            ->whereHas('subject.classroom.students', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->with(['subject', 'academicYear', 'questionBank'])
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found or you do not have access to it.');
        }

        // Attach session and result info
        $sessions = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->get();

        $exam->attempts_count = $sessions->count();
        $exam->latest_session = $sessions->last();

        $exam->best_result = \App\Models\ExamResult::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        return $this->success(
            new ExamResource($exam),
            'Exam details retrieved successfully'
        );
    }

    /**
     * Start the exam.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $exam = Exam::query()
            ->where('is_published', true)
            ->where('id', $id)
            ->whereHas('subject.classroom.students', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found or you do not have access to it.');
        }

        // 1. Check strict time (Start/End Time)
        $now = now();
        if ($exam->start_time && $now < $exam->start_time) {
            return $this->error('Exam has not started yet.', 403);
        }
        if ($exam->end_time && $now > $exam->end_time) {
            return $this->error('Exam has ended.', 403);
        }

        // 2. Check Token (if visible)
        if ($exam->is_token_visible) {
            $request->validate([
                'token' => 'required|string',
            ]);

            if ($request->token !== $exam->token) {
                return $this->error('Invalid exam token.', 403);
            }
        }

        // 3. Check Attempts
        // Check if there is an active session
        $activeSession = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('is_finished', false)
            ->first();

        if ($activeSession) {
            // If active session exists, just return it (Resume)
            return $this->success(
                [
                    'exam_session_id' => $activeSession->id,
                    'session' => new ExamSessionResource($activeSession),
                    'message' => 'Resuming existing session.'
                ],
                'Exam session resumed.'
            );
        }

        // Count finished sessions
        $attemptsCount = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('is_finished', true)
            ->count();

        if ($exam->max_attempts && $attemptsCount >= $exam->max_attempts) {
            return $this->error('Maximum attempts reached.', 403);
        }

        // 4. Create Session & Snapshot Questions
        try {
            return DB::transaction(function () use ($exam, $user, $now, $attemptsCount) {
                // Create Session
                $session = ExamSession::create([
                    'exam_id' => $exam->id,
                    'user_id' => $user->id,
                    'attempt_number' => $attemptsCount + 1,
                    'start_time' => $now,
                    'is_finished' => false,
                    'total_max_score' => 0, // Will be calculated
                    'total_score' => 0,
                ]);

                // Get Questions
                $questions = ExamQuestion::where('exam_id', $exam->id)->get();

                if ($exam->is_randomized_question) {
                    $questions = $questions->shuffle();
                }

                $totalMaxScore = 0;
                $detailsData = [];
                $order = 1;

                foreach ($questions as $question) {
                    // Calculate max score
                    $totalMaxScore += $question->score_value;

                    $detailsData[] = [
                        'id' => (string) Str::ulid(),
                        'exam_session_id' => $session->id,
                        'exam_question_id' => $question->id,
                        'question_number' => $order++,
                        'score_earned' => 0,
                        'is_flagged' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Initial insert
                ExamResultDetail::insert($detailsData);

                // Update max score in session
                $session->update(['total_max_score' => $totalMaxScore]);

                return $this->created(
                    [
                        'exam_session_id' => $session->id,
                        'session' => new ExamSessionResource($session),
                    ],
                    'Exam started successfully.'
                );
            });
        } catch (\Exception $e) {
            return $this->error('Failed to start exam: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get exam questions and status (Take Exam).
     */
    public function take(string $id): JsonResponse
    {
        $user = Auth::user();

        $exam = Exam::query()
            ->where('id', $id)
            ->first();

        if (! $exam) {
            return $this->notFound('Exam not found.');
        }

        // Get Active Session
        $session = ExamSession::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('No active session found. Please start the exam first.', 403);
        }

        // Calculate Remaining Time
        $now = Carbon::now();
        $startTime = Carbon::parse($session->start_time);

        // Duration limit
        $endTimeByDuration = $startTime->copy()->addMinutes($exam->duration);

        // Exam strict end time
        $hardEndTime = $exam->end_time ? Carbon::parse($exam->end_time) : null;

        // Real end time is the earlier of the two
        $realEndTime = $endTimeByDuration;
        if ($hardEndTime && $hardEndTime < $endTimeByDuration) {
            $realEndTime = $hardEndTime;
        }

        if ($now > $realEndTime) {
            // Time expired, auto-finish?
            // For now just return 0 remaining
            $remainingSeconds = 0;
        } else {
            $remainingSeconds = $now->diffInSeconds($realEndTime);
        }

        // Get Questions (Details)
        // We load the ExamQuestion snapshot info
        $questions = ExamResultDetail::query()
            ->where('exam_session_id', $session->id)
            ->with(['examQuestion']) // Load the question content
            ->orderBy('question_number')
            ->get();

        // Transform if needed to hide key_answer etc if they were in ExamQuestion (Model `ExamQuestion` already defines hidden? No, we should ensure we don't send key answers if they are in the model)
        // Check `ExamQuestion` model. `key_answer` is in fillable/casts. It is NOT hidden by default.
        // We MUST hide `key_answer` from the response.

        $questions->transform(function ($detail) {
            if ($detail->examQuestion) {
                $detail->examQuestion->makeHidden(['key_answer']);

                // If randomized answers, we might need to shuffle options here or if they were shuffled at snapshot time?
                // `ExamQuestion` stores `options`. If `is_randomized_answer` is on Exam, we should shuffle them here or in snapshot.
                // Ideally shuffle here for display.
            }
            return $detail;
        });

        return $this->success(
            [
                'exam' => new ExamResource($exam),
                'session' => new ExamSessionResource($session),
                'questions' => ExamResultDetailResource::collection($questions),
                'remaining_seconds' => $remainingSeconds,
            ],
            'Exam data retrieved.'
        );
    }

    /**
     * Save answer for a question.
     */
    public function saveAnswer(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'question_id' => 'required|string', // This is the ExamResultDetail ID
            'answer' => 'nullable', // string or array, depending on question type
            'is_flagged' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // Validation: Exam & Session
        $session = ExamSession::where('exam_id', $id)
            ->where('user_id', $user->id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('No active session found.', 403);
        }

        // Check if time is up (Soft check, allow a small buffer? Or strict?)
        // Let's rely on finish() for final enforcement, but here we can block if way past due.
        // For now, allow saving as long as session is not finished.

        $detail = ExamResultDetail::where('id', $request->question_id)
            ->where('exam_session_id', $session->id)
            ->with(['examQuestion'])
            ->first();

        if (! $detail) {
            return $this->notFound('Question (detail) not found within this session.');
        }

        // Update answer
        $answer = $request->answer;

        // Use ExamScoringService for auto-grading
        $scoringService = new \App\Services\ExamScoringService();
        $result = $scoringService->calculateDetailScore($detail->fill(['student_answer' => $answer]));

        $detail->student_answer = $answer;
        $detail->is_correct = $result['is_correct'];
        $detail->score_earned = $result['score'];
        $detail->is_flagged = $request->boolean('is_flagged', $detail->is_flagged);
        $detail->answered_at = now();
        $detail->save();

        return $this->success(
            [
                'question_id' => $detail->id,
                'is_answered' => true,
                'detail' => new \App\Http\Resources\Student\ExamResultDetailResource($detail),
            ],
            'Answer saved.'
        );
    }

    /**
     * Finish the exam.
     */
    public function finish(string $id): JsonResponse
    {
        $user = Auth::user();

        $session = ExamSession::where('exam_id', $id)
            ->where('user_id', $user->id)
            ->where('is_finished', false)
            ->first();

        if (! $session) {
            return $this->error('No active session found.', 403);
        }

        return DB::transaction(function () use ($session, $user, $id) {
            // 1. Update Session Status
            $session->update([
                'finish_time' => now(),
                'is_finished' => true,
            ]);

            // 2. Dispatch Scoring Job (Calculate final score asynchronously)
            \App\Jobs\CalculateExamScoreJob::dispatch($session);

            return $this->success(
                [
                    'exam_session_id' => $session->id,
                    'session' => new \App\Http\Resources\Student\ExamSessionResource($session),
                    'message' => 'Exam finished. Scoring is in progress.',
                    'finished_at' => $session->finish_time,
                ],
                'Exam finished successfully.'
            );
        });
    }
}
