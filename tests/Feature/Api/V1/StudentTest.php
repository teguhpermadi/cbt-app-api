<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\AcademicYear;
use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Student Management', function () {
    it('can list students', function () {
        User::factory()->count(3)->create(['user_type' => UserTypeEnum::STUDENT]);

        $response = $this->getJson('/api/v1/students');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show a student', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $response = $this->getJson("/api/v1/students/{$student->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $student->id,
                ],
            ]);
    });

    it('can create a student', function () {
        $data = [
            'name' => 'New Student',
            'username' => 'newstudent',
            'email' => 'newstudent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/students', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'newstudent@example.com',
            'user_type' => UserTypeEnum::STUDENT->value,
        ]);
    });

    it('can update a student', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
        $data = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/students/{$student->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Name',
        ]);
    });

    it('can soft-delete a student', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $response = $this->deleteJson("/api/v1/students/{$student->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($student);
    });

    it('can list available students', function () {
        $academicYear = AcademicYear::factory()->create();
        User::factory()->count(2)->create(['user_type' => UserTypeEnum::STUDENT]);

        $response = $this->getJson("/api/v1/students/available?academic_year_id={$academicYear->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    });

    it('can list trashed students', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
        $student->delete();

        $response = $this->getJson('/api/v1/students/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a student', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
        $student->delete();

        $response = $this->postJson("/api/v1/students/{$student->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($student);
    });

    it('can force delete a student', function () {
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
        $student->delete();

        $response = $this->deleteJson("/api/v1/students/{$student->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    });

    it('can bulk delete students', function () {
        $students = User::factory()->count(3)->create(['user_type' => UserTypeEnum::STUDENT]);
        $ids = $students->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/students/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($students as $student) {
            $this->assertSoftDeleted($student);
        }
    });

    it('can bulk update students', function () {
        $students = User::factory()->count(2)->create(['user_type' => UserTypeEnum::STUDENT]);
        $data = [
            'students' => [
                ['id' => $students[0]->id, 'name' => 'Bulk Update 1'],
                ['id' => $students[1]->id, 'name' => 'Bulk Update 2'],
            ],
        ];

        $response = $this->postJson('/api/v1/students/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $students[0]->id, 'name' => 'Bulk Update 1']);
        $this->assertDatabaseHas('users', ['id' => $students[1]->id, 'name' => 'Bulk Update 2']);
    });

    it('can search students by name', function () {
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Alice Johnson']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Bob Anderson']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Charlie Brown']);

        $response = $this->getJson('/api/v1/students?search=Bob');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Bob Anderson');
    });

    it('can search students by email', function () {
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'email' => 'alice@school.com']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'email' => 'bob@university.com']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'email' => 'charlie@school.com']);

        $response = $this->getJson('/api/v1/students?search=university');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.email', 'bob@university.com');
    });

    it('can search students by username', function () {
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'username' => 'alice123']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'username' => 'bob456']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'username' => 'charlie789']);

        $response = $this->getJson('/api/v1/students?search=bob');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.username', 'bob456');
    });

    it('can sort students by name ascending', function () {
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Zara']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Adam']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Michael']);

        $response = $this->getJson('/api/v1/students?sort_by=name&order=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Adam')
            ->assertJsonPath('data.data.1.name', 'Michael')
            ->assertJsonPath('data.data.2.name', 'Zara');
    });

    it('can sort students by name descending', function () {
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Zara']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Adam']);
        User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Michael']);

        $response = $this->getJson('/api/v1/students?sort_by=name&order=desc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Zara')
            ->assertJsonPath('data.data.1.name', 'Michael')
            ->assertJsonPath('data.data.2.name', 'Adam');
    });

    it('can paginate students with custom per_page', function () {
        User::factory()->count(25)->create(['user_type' => UserTypeEnum::STUDENT]);

        $response = $this->getJson('/api/v1/students?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data.data');
    });
});
