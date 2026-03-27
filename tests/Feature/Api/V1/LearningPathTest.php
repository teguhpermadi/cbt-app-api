<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\Classroom;
use App\Models\LearningPath;
use App\Models\Subject;
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

describe('LearningPath API', function () {
    it('can list learning paths', function () {
        LearningPath::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/learning-paths');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can filter learning paths by subject_id', function () {
        $subject = Subject::factory()->create();
        LearningPath::factory()->create(['subject_id' => $subject->id]);
        $otherSubject = Subject::factory()->create();
        LearningPath::factory()->count(2)->create(['subject_id' => $otherSubject->id]);

        $response = $this->getJson("/api/v1/learning-paths?subject_id={$subject->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can filter learning paths by classroom_id', function () {
        $classroom = Classroom::factory()->create();
        LearningPath::factory()->create(['classroom_id' => $classroom->id]);
        $otherClassroom = Classroom::factory()->create();
        LearningPath::factory()->count(2)->create(['classroom_id' => $otherClassroom->id]);

        $response = $this->getJson("/api/v1/learning-paths?classroom_id={$classroom->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can filter learning paths by is_published', function () {
        LearningPath::factory()->count(2)->create(['is_published' => true]);
        LearningPath::factory()->count(1)->create(['is_published' => false]);

        $response = $this->getJson('/api/v1/learning-paths?is_published=true');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    });

    it('can search learning paths by title', function () {
        LearningPath::factory()->create(['title' => 'Mathematics Basics']);
        LearningPath::factory()->create(['title' => 'Physics Advanced']);
        LearningPath::factory()->create(['title' => 'Chemistry Intro']);

        $response = $this->getJson('/api/v1/learning-paths?search=Math');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Mathematics Basics');
    });

    it('can create a learning path with auto order', function () {
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        $data = [
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'New Learning Path',
            'description' => 'A description',
            'is_published' => true,
        ];

        $response = $this->postJson('/api/v1/learning-paths', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_paths', [
            'title' => 'New Learning Path',
            'order' => 0,
        ]);
    });

    it('auto increments order for same subject and classroom', function () {
        $subject = Subject::factory()->create();
        $classroom = Classroom::factory()->create();

        $path1 = $this->postJson('/api/v1/learning-paths', [
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'Path 1',
        ]);

        $path2 = $this->postJson('/api/v1/learning-paths', [
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'Path 2',
        ]);

        $path1->assertStatus(201);
        $path2->assertStatus(201);

        $this->assertDatabaseHas('learning_paths', ['title' => 'Path 1', 'order' => 0]);
        $this->assertDatabaseHas('learning_paths', ['title' => 'Path 2', 'order' => 1]);
    });

    it('can show a learning path with relations', function () {
        $path = LearningPath::factory()->create();
        $path->units()->createMany([
            ['title' => 'Unit 1', 'order' => 0],
            ['title' => 'Unit 2', 'order' => 1],
        ]);

        $response = $this->getJson("/api/v1/learning-paths/{$path->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $path->id,
                ],
            ]);
    });

    it('can update a learning path', function () {
        $path = LearningPath::factory()->create();
        $data = ['title' => 'Updated Title'];

        $response = $this->putJson("/api/v1/learning-paths/{$path->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_paths', [
            'id' => $path->id,
            'title' => 'Updated Title',
        ]);
    });

    it('can soft delete a learning path', function () {
        $path = LearningPath::factory()->create();

        $response = $this->deleteJson("/api/v1/learning-paths/{$path->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($path);
    });

    it('can list trashed learning paths', function () {
        $path = LearningPath::factory()->create();
        $path->delete();

        $response = $this->getJson('/api/v1/learning-paths/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a learning path', function () {
        $path = LearningPath::factory()->create();
        $path->delete();

        $response = $this->postJson("/api/v1/learning-paths/{$path->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($path);
    });

    it('can force delete a learning path', function () {
        $path = LearningPath::factory()->create();
        $path->delete();

        $response = $this->deleteJson("/api/v1/learning-paths/{$path->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('learning_paths', ['id' => $path->id]);
    });

    it('can bulk delete learning paths', function () {
        $paths = LearningPath::factory()->count(3)->create();
        $ids = $paths->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/learning-paths/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($paths as $path) {
            $this->assertSoftDeleted($path);
        }
    });

    it('can reorder learning paths', function () {
        $path1 = LearningPath::factory()->create(['order' => 0]);
        $path2 = LearningPath::factory()->create(['order' => 1]);
        $path3 = LearningPath::factory()->create(['order' => 2]);

        $response = $this->postJson('/api/v1/learning-paths/reorder', [
            'items' => [
                ['id' => $path1->id, 'order' => 2],
                ['id' => $path2->id, 'order' => 0],
                ['id' => $path3->id, 'order' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_paths', ['id' => $path1->id, 'order' => 2]);
        $this->assertDatabaseHas('learning_paths', ['id' => $path2->id, 'order' => 0]);
        $this->assertDatabaseHas('learning_paths', ['id' => $path3->id, 'order' => 1]);
    });

    it('can paginate learning paths with custom per_page', function () {
        LearningPath::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/learning-paths?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    });
});
