<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\ReadingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Reading Material Management', function () {
    it('can list reading materials', function () {
        ReadingMaterial::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/reading-materials');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'Reading materials retrieved successfully',
            ]);
    });

    it('can show a reading material', function () {
        $material = ReadingMaterial::factory()->create();

        $response = $this->getJson("/api/v1/reading-materials/{$material->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $material->id,
                    'title' => $material->title,
                ],
            ]);
    });

    it('can create a reading material', function () {
        $data = [
            'title' => 'Sample Reading Material',
            'content' => 'Sample content for testing.',
        ];

        $response = $this->postJson('/api/v1/reading-materials', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reading material created successfully',
            ]);

        $this->assertDatabaseHas('reading_materials', [
            'title' => 'Sample Reading Material',
            'user_id' => $this->admin->id,
        ]);
    });

    it('can update a reading material', function () {
        $material = ReadingMaterial::factory()->create();
        $data = ['title' => 'Updated Title'];

        $response = $this->putJson("/api/v1/reading-materials/{$material->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reading material updated successfully',
            ]);

        $this->assertDatabaseHas('reading_materials', [
            'id' => $material->id,
            'title' => 'Updated Title',
        ]);
    });

    it('can soft-delete a reading material', function () {
        $material = ReadingMaterial::factory()->create();

        $response = $this->deleteJson("/api/v1/reading-materials/{$material->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($material);
    });

    it('can list trashed reading materials', function () {
        $material = ReadingMaterial::factory()->create();
        $material->delete();

        $response = $this->getJson('/api/v1/reading-materials/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a reading material', function () {
        $material = ReadingMaterial::factory()->create();
        $material->delete();

        $response = $this->postJson("/api/v1/reading-materials/{$material->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($material);
    });

    it('can force delete a reading material', function () {
        $material = ReadingMaterial::factory()->create();
        $material->delete();

        $response = $this->deleteJson("/api/v1/reading-materials/{$material->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reading_materials', ['id' => $material->id]);
    });

    it('can bulk delete reading materials', function () {
        $materials = ReadingMaterial::factory()->count(3)->create();
        $ids = $materials->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/reading-materials/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($materials as $material) {
            $this->assertSoftDeleted($material);
        }
    });

    it('can upload media to a reading material', function () {
        Storage::fake('public');
        $material = ReadingMaterial::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->postJson("/api/v1/reading-materials/{$material->id}/media", [
            'media' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully',
            ]);

        expect($material->fresh()->getMedia('reading_materials'))->toHaveCount(1);
    });
});
