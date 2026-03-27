<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\LearningPath;
use App\Models\LearningUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Teguh',
        'user_type' => UserTypeEnum::TEACHER,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

describe('LearningUnit API', function () {
    it('can list learning units', function () {
        $path = LearningPath::factory()->create();
        LearningUnit::factory()->count(3)->create(['learning_path_id' => $path->id]);

        $response = $this->getJson('/api/v1/learning-units');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can filter learning units by learning_path_id', function () {
        $path1 = LearningPath::factory()->create();
        $path2 = LearningPath::factory()->create();

        LearningUnit::factory()->count(2)->create(['learning_path_id' => $path1->id]);
        LearningUnit::factory()->count(1)->create(['learning_path_id' => $path2->id]);

        $response = $this->getJson("/api/v1/learning-units?learning_path_id={$path1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    });

    it('can search learning units by title', function () {
        $path = LearningPath::factory()->create();
        LearningUnit::factory()->create(['learning_path_id' => $path->id, 'title' => 'Introduction to Math']);
        LearningUnit::factory()->create(['learning_path_id' => $path->id, 'title' => 'Advanced Physics']);
        LearningUnit::factory()->create(['learning_path_id' => $path->id, 'title' => 'Basic Chemistry']);

        $response = $this->getJson('/api/v1/learning-units?search=Math');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Introduction to Math');
    });

    it('can create a learning unit with auto order', function () {
        $path = LearningPath::factory()->create();

        $data = [
            'learning_path_id' => $path->id,
            'title' => 'New Learning Unit',
            'xp_reward' => 50,
        ];

        $response = $this->postJson('/api/v1/learning-units', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_units', [
            'title' => 'New Learning Unit',
            'order' => 0,
        ]);
    });

    it('auto increments order within a learning path', function () {
        $path = LearningPath::factory()->create();

        $unit1 = $this->postJson('/api/v1/learning-units', [
            'learning_path_id' => $path->id,
            'title' => 'Unit 1',
        ]);

        $unit2 = $this->postJson('/api/v1/learning-units', [
            'learning_path_id' => $path->id,
            'title' => 'Unit 2',
        ]);

        $unit1->assertStatus(201);
        $unit2->assertStatus(201);

        $this->assertDatabaseHas('learning_units', ['title' => 'Unit 1', 'order' => 0]);
        $this->assertDatabaseHas('learning_units', ['title' => 'Unit 2', 'order' => 1]);
    });

    it('can show a learning unit with lessons', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $unit->lessons()->createMany([
            ['title' => 'Lesson 1', 'order' => 0, 'content_type' => 'reading'],
            ['title' => 'Lesson 2', 'order' => 1, 'content_type' => 'video'],
        ]);

        $response = $this->getJson("/api/v1/learning-units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $unit->id,
                ],
            ]);
    });

    it('can update a learning unit', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $data = ['title' => 'Updated Title', 'xp_reward' => 100];

        $response = $this->putJson("/api/v1/learning-units/{$unit->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_units', [
            'id' => $unit->id,
            'title' => 'Updated Title',
            'xp_reward' => 100,
        ]);
    });

    it('can delete a learning unit', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $response = $this->deleteJson("/api/v1/learning-units/{$unit->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($unit);
    });

    it('can bulk delete learning units', function () {
        $path = LearningPath::factory()->create();
        $units = LearningUnit::factory()->count(3)->create(['learning_path_id' => $path->id]);
        $ids = $units->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/learning-units/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($units as $unit) {
            $this->assertSoftDeleted($unit);
        }
    });

    it('can reorder learning units', function () {
        $path = LearningPath::factory()->create();
        $unit1 = LearningUnit::factory()->create(['learning_path_id' => $path->id, 'order' => 0]);
        $unit2 = LearningUnit::factory()->create(['learning_path_id' => $path->id, 'order' => 1]);
        $unit3 = LearningUnit::factory()->create(['learning_path_id' => $path->id, 'order' => 2]);

        $response = $this->postJson('/api/v1/learning-units/reorder', [
            'items' => [
                ['id' => $unit1->id, 'order' => 2],
                ['id' => $unit2->id, 'order' => 0],
                ['id' => $unit3->id, 'order' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_units', ['id' => $unit1->id, 'order' => 2]);
        $this->assertDatabaseHas('learning_units', ['id' => $unit2->id, 'order' => 0]);
        $this->assertDatabaseHas('learning_units', ['id' => $unit3->id, 'order' => 1]);
    });

    it('can paginate learning units with custom per_page', function () {
        $path = LearningPath::factory()->create();
        LearningUnit::factory()->count(15)->create(['learning_path_id' => $path->id]);

        $response = $this->getJson('/api/v1/learning-units?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    });
});
