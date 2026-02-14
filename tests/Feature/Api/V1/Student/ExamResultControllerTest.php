<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamResultControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_their_exam_results()
    {
        $student = User::factory()->student()->create();
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id
        ]);

        $examResult = ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'score_percent' => 85,
            'total_score' => 85,
            'is_passed' => true,
        ]);

        // Another student's result (should not see)
        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => User::factory()->student()->create()->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson(route('api.v1.student.exam-results.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $examResult->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'exam' => [
                            'id',
                            'subject' => [
                                'id',
                                'classroom' => ['id']
                            ],
                            'academic_year' => ['id']
                        ],
                        'total_score',
                        'score_percent',
                        'is_passed',
                    ]
                ]
            ]);
    }

    public function test_leaderboard_returns_ordered_results()
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $student3 = User::factory()->student()->create();

        $exam = Exam::factory()->create();

        // Create results with different scores
        ExamResult::factory()->create(['user_id' => $student1->id, 'exam_id' => $exam->id, 'total_score' => 90, 'score_percent' => 90]);
        ExamResult::factory()->create(['user_id' => $student2->id, 'exam_id' => $exam->id, 'total_score' => 100, 'score_percent' => 100]);
        ExamResult::factory()->create(['user_id' => $student3->id, 'exam_id' => $exam->id, 'total_score' => 80, 'score_percent' => 80]);

        $response = $this->actingAs($student1, 'sanctum')
            ->getJson(route('api.v1.student.exam-results.leaderboard', ['exam_id' => $exam->id]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        $this->assertEquals(100, $data[0]['total_score']);
        $this->assertEquals(90, $data[1]['total_score']);
        $this->assertEquals(80, $data[2]['total_score']);
    }

    public function test_leaderboard_filters_by_exam_id()
    {
        $student = User::factory()->student()->create();
        $exam1 = Exam::factory()->create();
        $exam2 = Exam::factory()->create();

        ExamResult::factory()->create(['user_id' => $student->id, 'exam_id' => $exam1->id, 'total_score' => 90]);
        ExamResult::factory()->create(['user_id' => $student->id, 'exam_id' => $exam2->id, 'total_score' => 80]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson(route('api.v1.student.exam-results.leaderboard', ['exam_id' => $exam1->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.exam.id', $exam1->id);
    }
}
