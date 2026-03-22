<?php

namespace App\Services;

use App\Models\User;
use App\Models\LearningLesson;
use App\Models\UserLearningProgress;
use App\Models\UserGamificationStats;
use App\Models\Badge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    /**
     * Laporkan penyelesaian pelajaran oleh siswa dan berikan XP.
     */
    public function completeLesson(User $user, LearningLesson $lesson)
    {
        return DB::transaction(function () use ($user, $lesson) {
            $progress = UserLearningProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'learning_lesson_id' => $lesson->id,
                ],
                [
                    'status' => 'completed',
                    'xp_earned' => $lesson->xp_reward,
                    'completed_at' => Carbon::now(),
                ]
            );

            $this->awardXp($user, $lesson->xp_reward);
            $this->checkAndAwardBadges($user);

            return $progress;
        });
    }

    /**
     * Berikan XP kepada pengguna dan perbarui statistik mereka.
     */
    public function awardXp(User $user, int $amount)
    {
        $stats = UserGamificationStats::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'level' => 1, 'streak_count' => 0]
        );

        $stats->total_xp += $amount;
        
        // Kalkulasi level sederhana: setiap 500 XP naik 1 level
        $newLevel = (int) floor($stats->total_xp / 500) + 1;
        $stats->level = $newLevel;
        
        $stats->last_activity_at = Carbon::now();
        $stats->save();

        return $stats;
    }

    /**
     * Periksa dan berikan lencana berdasarkan statistik pengguna.
     */
    public function checkAndAwardBadges(User $user)
    {
        $stats = $user->gamificationStats;
        if (!$stats) {
            $stats = UserGamificationStats::firstOrCreate(
                ['user_id' => $user->id],
                ['total_xp' => 0, 'level' => 1, 'streak_count' => 0]
            );
        }

        $unearnedBadges = Badge::whereDoesntHave('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        foreach ($unearnedBadges as $badge) {
            $shouldAward = false;

            switch ($badge->requirement_type) {
                case 'xp':
                    if ($stats->total_xp >= $badge->requirement_value) {
                        $shouldAward = true;
                    }
                    break;
                case 'lesson_count':
                    $lessonCount = $user->learningProgress()->where('status', 'completed')->count();
                    if ($lessonCount >= $badge->requirement_value) {
                        $shouldAward = true;
                    }
                    break;
                case 'streak':
                    if ($stats->streak_count >= $badge->requirement_value) {
                        $shouldAward = true;
                    }
                    break;
            }

            if ($shouldAward) {
                $user->badges()->attach($badge->id, ['awarded_at' => Carbon::now()]);
            }
        }
    }
}
