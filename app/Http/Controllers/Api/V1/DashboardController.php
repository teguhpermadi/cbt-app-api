<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Http\Resources\V1\ActivityLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

final class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = now();

        $studentsCount = User::where('user_type', UserTypeEnum::STUDENT)->count();
        $classroomsCount = Classroom::count();

        $ongoingExamsQuery = Exam::where('is_published', true)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);

        $ongoingExamsCount = $ongoingExamsQuery->count();
        $ongoingExams = $ongoingExamsQuery->with(['classrooms', 'subject'])
            ->latest('start_time')
            ->get();

        $recentActivities = Activity::where('causer_id', $request->user()->id)
            ->with(['subject'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'students_count' => $studentsCount,
                    'classrooms_count' => $classroomsCount,
                    'ongoing_exams_count' => $ongoingExamsCount,
                ],
                'ongoing_exams' => $ongoingExams->map(fn($exam) => [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject?->name,
                    'classrooms' => $exam->classrooms->pluck('name'),
                    'start_time' => $exam->start_time,
                    'end_time' => $exam->end_time,
                    'duration' => $exam->duration,
                ]),
                'recent_activities' => ActivityLogResource::collection($recentActivities),
            ],
        ]);
    }
}
