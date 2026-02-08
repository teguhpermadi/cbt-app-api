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

describe('Academic Year Management', function () {
    it('can list academic years', function () {
        AcademicYear::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/academic-years');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show an academic year', function () {
        $academicYear = AcademicYear::factory()->create();

        $response = $this->getJson("/api/v1/academic-years/{$academicYear->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $academicYear->id,
                ],
            ]);
    });

    it('can create an academic year', function () {
        $data = [
            'year' => '2023/2024',
            'semester' => 'Odd',
            'user_id' => $this->admin->id,
        ];

        $response = $this->postJson('/api/v1/academic-years', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('academic_years', [
            'year' => '2023/2024',
            'semester' => 'Odd',
        ]);
    });

    it('can update an academic year', function () {
        $academicYear = AcademicYear::factory()->create();
        $data = ['year' => '2024/2025', 'semester' => 'Even', 'user_id' => $this->admin->id];

        $response = $this->putJson("/api/v1/academic-years/{$academicYear->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('academic_years', [
            'id' => $academicYear->id,
            'year' => '2024/2025',
        ]);
    });

    it('can soft-delete an academic year', function () {
        $academicYear = AcademicYear::factory()->create();

        $response = $this->deleteJson("/api/v1/academic-years/{$academicYear->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($academicYear);
    });

    it('can list trashed academic years', function () {
        $academicYear = AcademicYear::factory()->create();
        $academicYear->delete();

        $response = $this->getJson('/api/v1/academic-years/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore an academic year', function () {
        $academicYear = AcademicYear::factory()->create();
        $academicYear->delete();

        $response = $this->postJson("/api/v1/academic-years/{$academicYear->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($academicYear);
    });

    it('can force delete an academic year', function () {
        $academicYear = AcademicYear::factory()->create();
        $academicYear->delete();

        $response = $this->deleteJson("/api/v1/academic-years/{$academicYear->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('academic_years', ['id' => $academicYear->id]);
    });

    it('can bulk delete academic years', function () {
        $academicYears = AcademicYear::factory()->count(3)->create();
        $ids = $academicYears->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/academic-years/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($academicYears as $academicYear) {
            $this->assertSoftDeleted($academicYear);
        }
    });

    it('can bulk update academic years', function () {
        $academicYears = AcademicYear::factory()->count(2)->create();
        $data = [
            'academic_years' => [
                ['id' => $academicYears[0]->id, 'year' => '2025/2026'],
                ['id' => $academicYears[1]->id, 'year' => '2026/2027'],
            ],
        ];

        $response = $this->postJson('/api/v1/academic-years/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('academic_years', ['id' => $academicYears[0]->id, 'year' => '2025/2026']);
        $this->assertDatabaseHas('academic_years', ['id' => $academicYears[1]->id, 'year' => '2026/2027']);
    });
});
