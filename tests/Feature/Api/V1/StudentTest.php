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
});
