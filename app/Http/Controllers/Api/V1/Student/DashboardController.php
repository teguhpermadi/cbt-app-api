<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        // 1. Stats: XP, Level, Streak
        $totalXp = (int) ExamResult::where('user_id', $user->id)->sum('total_score');
        $level = (int) ($totalXp / 1000) + 1;
        $xpToNextLevel = 1000 - ($totalXp % 1000);
        $progressToNextLevel = ($totalXp % 1000) / 10; // percentage

        // Streak calculation
        $streak = $this->calculateStreak($user->id);

        /**
         * 2. Active Exams (Exams that need to be taken)
         * Precision matching: Student Classroom + Academic Year combo
         */
        $enrollments = DB::table('classroom_users')
            ->where('user_id', $user->id)
            ->get(['classroom_id', 'academic_year_id']);

        $activeExams = Exam::where('is_published', true)
            ->where(function ($query) use ($enrollments) {
                if ($enrollments->isEmpty()) {
                    $query->whereRaw('1 = 0'); // No access if no enrollments
                    return;
                }

                foreach ($enrollments as $enrollment) {
                    $query->orWhere(function ($q) use ($enrollment) {
                        $q->where('academic_year_id', $enrollment->academic_year_id)
                            ->whereHas('classrooms', fn($c) => $c->where('classrooms.id', $enrollment->classroom_id));
                    });
                }
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('start_time')->orWhere('start_time', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')->orWhere('end_time', '>=', $now);
            })
            ->with(['subject'])
            ->get()
            ->map(function ($exam) use ($user) {
                $session = ExamSession::where('exam_id', $exam->id)
                    ->where('user_id', $user->id)
                    ->orderBy('attempt_number', 'desc')
                    ->first();

                // Check if they have already finished and reached max attempts
                if ($exam->max_attempts && $session && $session->attempt_number >= $exam->max_attempts && $session->is_finished) {
                    return null; // Don't show if max attempts reached
                }

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject?->name,
                    'status' => $session ? ($session->is_finished ? 'Finished' : 'In Progress') : 'Not Started',
                    'progress' => $session ? round(($session->examResultDetails()->whereNotNull('student_answer')->count() / max(1, $session->examResultDetails()->count())) * 100) : 0,
                    'icon' => $this->getIconForSubject($exam->subject?->name ?? ''),
                    'color' => $this->getColorForSubject($exam->subject),
                    'end_time' => $exam->end_time ? $exam->end_time->toIso8601String() : null,
                ];
            })->filter()->values();

        // 3. Upcoming Exams
        $upcomingExams = Exam::where('is_published', true)
            ->where(function ($query) use ($enrollments) {
                if ($enrollments->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                foreach ($enrollments as $enrollment) {
                    $query->orWhere(function ($q) use ($enrollment) {
                        $q->where('academic_year_id', $enrollment->academic_year_id)
                            ->whereHas('classrooms', fn($c) => $c->where('classrooms.id', $enrollment->classroom_id));
                    });
                }
            })
            ->where('start_time', '>', $now)
            ->with(['subject'])
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get()
            ->map(function ($exam) use ($now) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject?->name,
                    'start_time' => $exam->start_time ? $exam->start_time->toIso8601String() : null,
                    'end_time' => $exam->end_time ? $exam->end_time->toIso8601String() : null,
                    'time_label' => $this->getTimeLabel($exam->start_time, $now),
                    'meta' => ($exam->start_time ? $exam->start_time->format('H:i') : '') . ' • Duration: ' . $exam->duration . 'm',
                    'color' => 'blue',
                    'icon' => 'calendar_month',
                ];
            });

        return response()->json([
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                ],
                'stats' => [
                    'xp' => $totalXp,
                    'level' => $level,
                    'streak' => $streak,
                    'xp_to_next' => $xpToNextLevel,
                    'next_rank' => $this->getNextRank($level),
                    'progress_to_next' => $progressToNextLevel,
                ],
                'active_exams' => $activeExams,
                'upcoming_exams' => $upcomingExams,
            ],
        ]);
    }

    private function calculateStreak(string $userId): int
    {
        $dates = ExamSession::where('user_id', $userId)
            ->select(DB::raw('DATE(start_time) as date'))
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date');

        if ($dates->isEmpty()) return 0;

        $streak = 0;
        $currentDate = now()->startOfDay();

        // Check if latest date is today or yesterday
        $latestDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
        if ($latestDate->diffInDays($currentDate) > 1) {
            return 0;
        }

        foreach ($dates as $date) {
            $carbonDate = \Carbon\Carbon::parse($date)->startOfDay();
            if ($carbonDate->diffInDays($currentDate) <= 1) {
                $streak++;
                $currentDate = $carbonDate;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function getIconForSubject(string $name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'design') || str_contains($name, 'art')) return 'brush';
        if (str_contains($name, 'code') || str_contains($name, 'react') || str_contains($name, 'program')) return 'code';
        if (str_contains($name, 'math') || str_contains($name, 'calculus')) return 'functions';
        return 'book';
    }

    private function getColorForSubject($subject): string
    {
        if ($subject && $subject->color) return $subject->color;

        $name = $subject ? strtolower($subject->name) : '';
        if (str_contains($name, 'design')) return 'blue';
        if (str_contains($name, 'react')) return 'amber';
        if (str_contains($name, 'math')) return 'rose';
        return 'primary';
    }

    private function getTimeLabel($startTime, \Carbon\Carbon $now): string
    {
        if (!$startTime) return 'Available Now';

        if ($startTime->isToday()) {
            if ($startTime->lte($now)) return 'In Progress';
            return 'In ' . $startTime->diffInHours($now) . ' Hours';
        }
        if ($startTime->isTomorrow()) return 'Tomorrow';
        return $startTime->format('l');
    }

    private function getNextRank(int $level): string
    {
        if ($level < 5) return 'Novice';
        if ($level < 10) return 'Apprentice';
        if ($level < 15) return 'Master';
        return 'Grandmaster';
    }
}
