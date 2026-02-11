<?php

namespace Tests\Feature\Api\V1\Student;

use App\Enums\QuestionTypeEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_student_can_list_available_exams_assigned_to_their_classroom()
    {
        // 1. Setup Student, Class, Subject
        $academicYear = AcademicYear::factory()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);

        // Enroll student to classroom
        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);

        // 2. Create Exams
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
        ]);

        // Exam from another class (Should not see)
        $otherClass = Classroom::factory()->create();
        $otherSubject = Subject::factory()->create(['classroom_id' => $otherClass->id]);
        $otherExam = Exam::factory()->create([
            'subject_id' => $otherSubject->id,
            'is_published' => true,
        ]);

        // 3. Act
        $response = $this->actingAs($student, 'sanctum')
            ->getJson(route('api.v1.student.exams.index'));

        // 4. Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $exam->id);
    }

    public function test_student_can_start_exam_creates_session_and_snapshots()
    {
        $academicYear = AcademicYear::factory()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::factory()->create();
        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);

        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
            'is_token_visible' => false,
            'start_time' => now()->subDay(),
            'end_time' => now()->addDay(),
            'duration' => 60,
        ]);

        // Create Questions for the exam (Assuming ExamQuestion directly or via QuestionBank?)
        // The implementation uses ExamQuestion::where('exam_id', ...)
        // So we need to seed ExamQuestions.
        $examQuestion = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'score_value' => 10,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson(route('api.v1.student.exams.start', $exam->id));

        $response->dump();

        $response->assertCreated();

        // Assert Session Created
        $this->assertDatabaseHas('exam_sessions', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
        ]);

        // Assert Result Details (Snapshot) created
        $session = ExamSession::first();
        $this->assertDatabaseHas('exam_result_details', [
            'exam_session_id' => $session->id,
            'exam_question_id' => $examQuestion->id,
        ]);
    }

    public function test_student_can_take_exam_returns_current_session_data()
    {
        $academicYear = AcademicYear::factory()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::factory()->create();
        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);

        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
            'is_token_visible' => false,
            'start_time' => now()->subDay(),
            'end_time' => now()->addDay(),
            'duration' => 60,
        ]);

        $examQuestion = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'key_answer' => 'A', // Should be hidden
        ]);

        // Manually start session
        $this->actingAs($student, 'sanctum')
            ->postJson(route('api.v1.student.exams.start', $exam->id));

        $response = $this->actingAs($student, 'sanctum')
            ->getJson(route('api.v1.student.exams.take', $exam->id));

        $response->assertOk()
            ->assertJsonStructure(['data' => ['exam', 'session', 'questions', 'remaining_seconds']])
            ->assertJsonPath('data.exam.id', $exam->id);

        // Assert key_answer hidden
        $questions = $response->json('data.questions');
        $this->assertArrayNotHasKey('key_answer', $questions[0]['exam_question']);
    }

    public function test_student_can_save_answer_auto_grading()
    {
        $academicYear = AcademicYear::factory()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::factory()->create();
        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);

        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
            'is_token_visible' => false,
            'start_time' => now()->subDay(),
            'end_time' => now()->addDay(),
        ]);

        $examQuestion = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => QuestionTypeEnum::MULTIPLE_CHOICE, // Should use valid enum
            'key_answer' => 'A',
            'score_value' => 10,
        ]);

        // Start Exam
        $this->actingAs($student, 'sanctum')->postJson(route('api.v1.student.exams.start', $exam->id));

        $session = ExamSession::first();
        $detail = ExamResultDetail::where('exam_session_id', $session->id)->first();

        // Submit Correct Answer
        $response = $this->actingAs($student, 'sanctum')
            ->postJson(route('api.v1.student.exams.answer', $exam->id), [
                'question_id' => $detail->id,
                'answer' => 'A',
            ]);

        $response->assertOk();

        // Check DB for update and scoring
        $this->assertDatabaseHas('exam_result_details', [
            'id' => $detail->id,
            'student_answer' => '"A"', // JSON encoded string/array? Casts handles it?
            // Eloquent casts array to JSON string in DB. "A" -> "\"A\"" if cast as array?
            // Or if we pass "A" to simple string column?
            // ExamResultDetail casts 'student_answer' => 'array'.
            // So if we pass "A", Laravel casts ["A"]? Or just "A"?
            // Let's check logic: Input string "A". Database stores JSON.
            // If cast is array, assigning string might fail or be cast to ["A"]?
            // Wait, if cast is array, we should pass array or it encodes whatever?
            // Usually we encode to json.
            'is_correct' => true,
            'score_earned' => 10,
        ]);
    }

    public function test_student_can_finish_exam_calculates_score()
    {
        $academicYear = AcademicYear::factory()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::factory()->create();
        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);

        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
            'is_token_visible' => false,
            'start_time' => now()->subDay(),
            'end_time' => now()->addDay(),
            'passing_score' => 60,
        ]);

        $examQuestion = ExamQuestion::factory()->create([
            'exam_id' => $exam->id,
            'question_type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'key_answer' => 'A',
            'score_value' => 100,
        ]);

        // Start
        $this->actingAs($student, 'sanctum')->postJson(route('api.v1.student.exams.start', $exam->id));
        $session = ExamSession::first();
        $detail = ExamResultDetail::first();

        // Answer Correctly
        $detail->update([
            'student_answer' => 'A',
            'is_correct' => true,
            'score_earned' => 100
        ]);

        // Finish
        $response = $this->actingAs($student, 'sanctum')
            ->postJson(route('api.v1.student.exams.finish', $exam->id));

        $response->assertOk();

        // Check Session Finished
        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'is_finished' => true,
            'total_score' => 100,
        ]);

        // Check Exam Result Created
        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'total_score' => 100,
            'is_passed' => true,
        ]);
    }
}
