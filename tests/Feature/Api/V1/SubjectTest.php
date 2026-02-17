<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Subject Management', function () {
    it('can list subjects', function () {
        Subject::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/subjects');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show a subject', function () {
        $subject = Subject::factory()->create();

        $response = $this->getJson("/api/v1/subjects/{$subject->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $subject->id,
                ],
            ]);
    });

    it('can create a subject', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create();

        $data = [
            'name' => 'Mathematics',
            'code' => 'MATH101',
            'user_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
            'classroom_id' => $classroom->id,
        ];

        $response = $this->postJson('/api/v1/subjects', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);
    });

    it('can update a subject', function () {
        $subject = Subject::factory()->create();
        $data = ['name' => 'Updated Subject'];

        $response = $this->putJson("/api/v1/subjects/{$subject->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Updated Subject',
        ]);
    });

    it('can soft-delete a subject', function () {
        $subject = Subject::factory()->create();

        $response = $this->deleteJson("/api/v1/subjects/{$subject->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($subject);
    });

    it('can list trashed subjects', function () {
        $subject = Subject::factory()->create();
        $subject->delete();

        $response = $this->getJson('/api/v1/subjects/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a subject', function () {
        $subject = Subject::factory()->create();
        $subject->delete();

        $response = $this->postJson("/api/v1/subjects/{$subject->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($subject);
    });

    it('can force delete a subject', function () {
        $subject = Subject::factory()->create();
        $subject->delete();

        $response = $this->deleteJson("/api/v1/subjects/{$subject->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    });

    it('can bulk delete subjects', function () {
        $subjects = Subject::factory()->count(3)->create();
        $ids = $subjects->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/subjects/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($subjects as $subject) {
            $this->assertSoftDeleted($subject);
        }
    });

    it('can bulk update subjects', function () {
        $subjects = Subject::factory()->count(2)->create();
        $data = [
            'subjects' => [
                ['id' => $subjects[0]->id, 'name' => 'Bulk Update 1'],
                ['id' => $subjects[1]->id, 'name' => 'Bulk Update 2'],
            ],
        ];

        $response = $this->postJson('/api/v1/subjects/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('subjects', ['id' => $subjects[0]->id, 'name' => 'Bulk Update 1']);
        $this->assertDatabaseHas('subjects', ['id' => $subjects[1]->id, 'name' => 'Bulk Update 2']);
    });

    it('can search subjects by name', function () {
        Subject::factory()->create(['name' => 'Mathematics']);
        Subject::factory()->create(['name' => 'Physics']);
        Subject::factory()->create(['name' => 'Chemistry']);

        $response = $this->getJson('/api/v1/subjects?search=Physics');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Physics');
    });

    it('can search subjects by code', function () {
        Subject::factory()->create(['code' => 'MATH101']);
        Subject::factory()->create(['code' => 'PHYS201']);
        Subject::factory()->create(['code' => 'CHEM301']);

        $response = $this->getJson('/api/v1/subjects?search=PHYS');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.code', 'PHYS201');
    });

    it('can search subjects by description', function () {
        Subject::factory()->create(['description' => 'Introduction to Algebra']);
        Subject::factory()->create(['description' => 'Advanced Calculus']);
        Subject::factory()->create(['description' => 'Basic Geometry']);

        $response = $this->getJson('/api/v1/subjects?search=Calculus');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.description', 'Advanced Calculus');
    });

    it('can sort subjects by name ascending', function () {
        Subject::factory()->create(['name' => 'Zoology']);
        Subject::factory()->create(['name' => 'Anatomy']);
        Subject::factory()->create(['name' => 'Microbiology']);

        $response = $this->getJson('/api/v1/subjects?sort_by=name&order=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Anatomy')
            ->assertJsonPath('data.data.1.name', 'Microbiology')
            ->assertJsonPath('data.data.2.name', 'Zoology');
    });

    it('can sort subjects by name descending', function () {
        Subject::factory()->create(['name' => 'Zoology']);
        Subject::factory()->create(['name' => 'Anatomy']);
        Subject::factory()->create(['name' => 'Microbiology']);

        $response = $this->getJson('/api/v1/subjects?sort_by=name&order=desc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Zoology')
            ->assertJsonPath('data.data.1.name', 'Microbiology')
            ->assertJsonPath('data.data.2.name', 'Anatomy');
    });

    it('can paginate subjects with custom per_page', function () {
        Subject::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/subjects?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    });
});
