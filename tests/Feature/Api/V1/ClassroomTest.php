<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Classroom;
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

describe('Classroom Management', function () {
    it('can list classrooms', function () {
        Classroom::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/classrooms');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show a classroom', function () {
        $classroom = Classroom::factory()->create();

        $response = $this->getJson("/api/v1/classrooms/{$classroom->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $classroom->id,
                ],
            ]);
    });

    it('can create a classroom', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $academicYear = AcademicYear::factory()->create();

        $data = [
            'name' => 'Class A',
            'code' => 'CLSA',
            'level' => '10',
            'user_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
        ];

        $response = $this->postJson('/api/v1/classrooms', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('classrooms', [
            'name' => 'Class A',
            'user_id' => $teacher->id,
        ]);
    });

    it('can update a classroom', function () {
        $classroom = Classroom::factory()->create();
        $data = ['name' => 'Updated Class'];

        $response = $this->putJson("/api/v1/classrooms/{$classroom->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'name' => 'Updated Class',
        ]);
    });

    it('can soft-delete a classroom', function () {
        $classroom = Classroom::factory()->create();

        $response = $this->deleteJson("/api/v1/classrooms/{$classroom->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($classroom);
    });

    it('can list trashed classrooms', function () {
        $classroom = Classroom::factory()->create();
        $classroom->delete();

        $response = $this->getJson('/api/v1/classrooms/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a classroom', function () {
        $classroom = Classroom::factory()->create();
        $classroom->delete();

        $response = $this->postJson("/api/v1/classrooms/{$classroom->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($classroom);
    });

    it('can force delete a classroom', function () {
        $classroom = Classroom::factory()->create();
        $classroom->delete();

        $response = $this->deleteJson("/api/v1/classrooms/{$classroom->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
    });

    it('can bulk delete classrooms', function () {
        $classrooms = Classroom::factory()->count(3)->create();
        $ids = $classrooms->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/classrooms/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($classrooms as $classroom) {
            $this->assertSoftDeleted($classroom);
        }
    });

    it('can bulk update classrooms', function () {
        $classrooms = Classroom::factory()->count(2)->create();
        $data = [
            'classrooms' => [
                ['id' => $classrooms[0]->id, 'name' => 'Bulk Update 1'],
                ['id' => $classrooms[1]->id, 'name' => 'Bulk Update 2'],
            ],
        ];

        $response = $this->postJson('/api/v1/classrooms/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('classrooms', ['id' => $classrooms[0]->id, 'name' => 'Bulk Update 1']);
        $this->assertDatabaseHas('classrooms', ['id' => $classrooms[1]->id, 'name' => 'Bulk Update 2']);
    });

    it('can sync students to a classroom', function () {
        $classroom = Classroom::factory()->create();
        $academicYear = AcademicYear::factory()->create();
        $students = User::factory()->count(3)->create(['user_type' => UserTypeEnum::STUDENT]);
        $studentIds = $students->pluck('id')->toArray();

        $response = $this->postJson("/api/v1/classrooms/{$classroom->id}/sync", [
            'student_ids' => $studentIds,
            'academic_year_id' => $academicYear->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('classroom_users', 3);
        foreach ($studentIds as $id) {
            $this->assertDatabaseHas('classroom_users', [
                'classroom_id' => $classroom->id,
                'user_id' => $id,
                'academic_year_id' => $academicYear->id,
            ]);
        }
    });
});
