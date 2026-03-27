<?php

declare(strict_types=1);

use App\Enums\LearningContentType;
use App\Enums\UserTypeEnum;
use App\Models\LearningLesson;
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

describe('LearningLesson API', function () {
    it('can list learning lessons', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        LearningLesson::factory()->count(3)->create(['learning_unit_id' => $unit->id]);

        $response = $this->getJson('/api/v1/learning-lessons');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can filter learning lessons by learning_unit_id', function () {
        $path = LearningPath::factory()->create();
        $unit1 = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $unit2 = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        LearningLesson::factory()->count(2)->create(['learning_unit_id' => $unit1->id]);
        LearningLesson::factory()->count(1)->create(['learning_unit_id' => $unit2->id]);

        $response = $this->getJson("/api/v1/learning-lessons?learning_unit_id={$unit1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    });

    it('can search learning lessons by title', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'title' => 'Introduction to Algebra']);
        LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'title' => 'Advanced Geometry']);
        LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'title' => 'Basic Trigonometry']);

        $response = $this->getJson('/api/v1/learning-lessons?search=Algebra');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Introduction to Algebra');
    });

    it('can create a learning lesson with reading content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Reading Lesson',
            'content_type' => LearningContentType::READING->value,
            'content_data' => [
                'content' => 'This is the reading content.',
            ],
            'xp_reward' => 10,
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Reading Lesson',
            'content_type' => LearningContentType::READING->value,
            'order' => 0,
        ]);
    });

    it('can create a learning lesson with video content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Video Lesson',
            'content_type' => LearningContentType::VIDEO->value,
            'content_data' => [
                'url' => 'https://example.com/video.mp4',
                'duration' => 300,
            ],
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Video Lesson',
            'content_type' => LearningContentType::VIDEO->value,
        ]);
    });

    it('can create a learning lesson with audio content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Audio Lesson',
            'content_type' => LearningContentType::AUDIO->value,
            'content_data' => [
                'url' => 'https://example.com/audio.mp3',
                'duration' => 180,
            ],
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Audio Lesson',
            'content_type' => LearningContentType::AUDIO->value,
        ]);
    });

    it('can create a learning lesson with web_link content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Web Link Lesson',
            'content_type' => LearningContentType::WEB_LINK->value,
            'content_data' => [
                'url' => 'https://example.com/article',
                'title' => 'External Article',
            ],
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Web Link Lesson',
            'content_type' => LearningContentType::WEB_LINK->value,
        ]);
    });

    it('can create a learning lesson with quiz content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Quiz Lesson',
            'content_type' => LearningContentType::QUIZ->value,
            'content_data' => [
                'question_count' => 10,
            ],
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Quiz Lesson',
            'content_type' => LearningContentType::QUIZ->value,
        ]);
    });

    it('can create a learning lesson with survey content type', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Survey Lesson',
            'content_type' => LearningContentType::SURVEY->value,
            'content_data' => [
                'questions' => 5,
            ],
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('learning_lessons', [
            'title' => 'Survey Lesson',
            'content_type' => LearningContentType::SURVEY->value,
        ]);
    });

    it('auto increments order within a learning unit', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $lesson1 = $this->postJson('/api/v1/learning-lessons', [
            'learning_unit_id' => $unit->id,
            'title' => 'Lesson 1',
            'content_type' => LearningContentType::READING->value,
        ]);

        $lesson2 = $this->postJson('/api/v1/learning-lessons', [
            'learning_unit_id' => $unit->id,
            'title' => 'Lesson 2',
            'content_type' => LearningContentType::VIDEO->value,
        ]);

        $lesson1->assertStatus(201);
        $lesson2->assertStatus(201);

        $this->assertDatabaseHas('learning_lessons', ['title' => 'Lesson 1', 'order' => 0]);
        $this->assertDatabaseHas('learning_lessons', ['title' => 'Lesson 2', 'order' => 1]);
    });

    it('can show a learning lesson', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $lesson = LearningLesson::factory()->create(['learning_unit_id' => $unit->id]);

        $response = $this->getJson("/api/v1/learning-lessons/{$lesson->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $lesson->id,
                ],
            ]);
    });

    it('can update a learning lesson', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $lesson = LearningLesson::factory()->create(['learning_unit_id' => $unit->id]);
        $data = ['title' => 'Updated Title', 'xp_reward' => 20];

        $response = $this->putJson("/api/v1/learning-lessons/{$lesson->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_lessons', [
            'id' => $lesson->id,
            'title' => 'Updated Title',
            'xp_reward' => 20,
        ]);
    });

    it('can delete a learning lesson', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $lesson = LearningLesson::factory()->create(['learning_unit_id' => $unit->id]);

        $response = $this->deleteJson("/api/v1/learning-lessons/{$lesson->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($lesson);
    });

    it('can bulk delete learning lessons', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        $lessons = LearningLesson::factory()->count(3)->create(['learning_unit_id' => $unit->id]);
        $ids = $lessons->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/learning-lessons/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($lessons as $lesson) {
            $this->assertSoftDeleted($lesson);
        }
    });

    it('can reorder learning lessons', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $lesson1 = LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'order' => 0]);
        $lesson2 = LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'order' => 1]);
        $lesson3 = LearningLesson::factory()->create(['learning_unit_id' => $unit->id, 'order' => 2]);

        $response = $this->postJson('/api/v1/learning-lessons/reorder', [
            'items' => [
                ['id' => $lesson1->id, 'order' => 2],
                ['id' => $lesson2->id, 'order' => 0],
                ['id' => $lesson3->id, 'order' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('learning_lessons', ['id' => $lesson1->id, 'order' => 2]);
        $this->assertDatabaseHas('learning_lessons', ['id' => $lesson2->id, 'order' => 0]);
        $this->assertDatabaseHas('learning_lessons', ['id' => $lesson3->id, 'order' => 1]);
    });

    it('can paginate learning lessons with custom per_page', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);
        LearningLesson::factory()->count(15)->create(['learning_unit_id' => $unit->id]);

        $response = $this->getJson('/api/v1/learning-lessons?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    });

    it('validates content_type must be valid enum value', function () {
        $path = LearningPath::factory()->create();
        $unit = LearningUnit::factory()->create(['learning_path_id' => $path->id]);

        $data = [
            'learning_unit_id' => $unit->id,
            'title' => 'Invalid Lesson',
            'content_type' => 'invalid_type',
        ];

        $response = $this->postJson('/api/v1/learning-lessons', $data);

        $response->assertStatus(422);
    });
});
