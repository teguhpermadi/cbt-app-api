<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\LearningLesson;
use App\Models\LearningPath;
use App\Models\LearningUnit;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_earns_xp_on_lesson_completion()
    {
        $user = User::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        
        $path = LearningPath::create([
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'user_id' => $user->id,
            'title' => 'Test Path',
        ]);

        $unit = LearningUnit::create([
            'learning_path_id' => $path->id,
            'title' => 'Test Unit',
            'order' => 1,
            'xp_reward' => 50,
        ]);

        $lesson = LearningLesson::create([
            'learning_unit_id' => $unit->id,
            'title' => 'Test Lesson',
            'content_type' => 'reading',
            'xp_reward' => 100,
            'order' => 1,
        ]);

        $service = new GamificationService();
        $service->completeLesson($user, $lesson);

        $this->assertDatabaseHas('user_learning_progress', [
            'user_id' => $user->id,
            'learning_lesson_id' => $lesson->id,
            'status' => 'completed',
            'xp_earned' => 100,
        ]);

        $this->assertDatabaseHas('user_gamification_stats', [
            'user_id' => $user->id,
            'total_xp' => 100,
            'level' => 1,
        ]);
    }

    public function test_user_earns_badge_on_xp_threshold()
    {
        $user = User::factory()->create();
        
        $badge = Badge::create([
            'name' => 'XP Starter',
            'requirement_type' => 'xp',
            'requirement_value' => 100,
        ]);

        $service = new GamificationService();
        $service->awardXp($user, 100);
        $service->checkAndAwardBadges($user->fresh());

        $this->assertTrue($user->fresh()->badges->contains($badge->id));
    }
}
