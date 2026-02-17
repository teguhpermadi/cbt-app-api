<?php

declare(strict_types=1);

use App\Models\User;
use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Teacher Management', function () {
    it('can list teachers', function () {
        User::factory()->count(3)->create(['user_type' => UserTypeEnum::TEACHER]);

        $response = $this->getJson('/api/v1/teachers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'Teachers retrieved successfully',
            ]);
    });

    it('can show a teacher', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);

        $response = $this->getJson("/api/v1/teachers/{$teacher->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $teacher->id,
                    'email' => $teacher->email,
                ],
            ]);
    });

    it('can create a teacher', function () {
        $data = [
            'name' => 'New Teacher',
            'username' => 'newteacher',
            'email' => 'newteacher@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/teachers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Teacher created successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newteacher@example.com',
            'user_type' => UserTypeEnum::TEACHER->value,
        ]);
    });

    it('can update a teacher', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $data = ['name' => 'Updated Name'];

        $response = $this->putJson("/api/v1/teachers/{$teacher->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Teacher updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $teacher->id,
            'name' => 'Updated Name',
        ]);
    });

    it('can soft-delete a teacher', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);

        $response = $this->deleteJson("/api/v1/teachers/{$teacher->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($teacher);
    });

    it('can list trashed teachers', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $teacher->delete();

        $response = $this->getJson('/api/v1/teachers/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a teacher', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $teacher->delete();

        $response = $this->postJson("/api/v1/teachers/{$teacher->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($teacher);
    });

    it('can force delete a teacher', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $teacher->delete();

        $response = $this->deleteJson("/api/v1/teachers/{$teacher->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    });

    it('can bulk delete teachers', function () {
        $teachers = User::factory()->count(3)->create(['user_type' => UserTypeEnum::TEACHER]);
        $ids = $teachers->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/teachers/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($teachers as $teacher) {
            $this->assertSoftDeleted($teacher);
        }
    });

    it('can bulk update teachers', function () {
        $teachers = User::factory()->count(2)->create(['user_type' => UserTypeEnum::TEACHER]);
        $data = [
            'teachers' => [
                ['id' => $teachers[0]->id, 'name' => 'Bulk Update 1'],
                ['id' => $teachers[1]->id, 'name' => 'Bulk Update 2'],
            ],
        ];

        $response = $this->postJson('/api/v1/teachers/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $teachers[0]->id, 'name' => 'Bulk Update 1']);
        $this->assertDatabaseHas('users', ['id' => $teachers[1]->id, 'name' => 'Bulk Update 2']);
    });

    it('can search teachers by name', function () {
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'John Doe']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Jane Smith']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Bob Wilson']);

        $response = $this->getJson('/api/v1/teachers?search=Jane');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Jane Smith');
    });

    it('can search teachers by email', function () {
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'email' => 'john@example.com']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'email' => 'jane@test.com']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'email' => 'bob@example.com']);

        $response = $this->getJson('/api/v1/teachers?search=test');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.email', 'jane@test.com');
    });

    it('can search teachers by username', function () {
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'username' => 'johndoe']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'username' => 'janesmith']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'username' => 'bobwilson']);

        $response = $this->getJson('/api/v1/teachers?search=jane');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.username', 'janesmith');
    });

    it('can sort teachers by name ascending', function () {
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Charlie']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Alice']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Bob']);

        $response = $this->getJson('/api/v1/teachers?sort_by=name&order=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Alice')
            ->assertJsonPath('data.data.1.name', 'Bob')
            ->assertJsonPath('data.data.2.name', 'Charlie');
    });

    it('can sort teachers by name descending', function () {
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Charlie']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Alice']);
        User::factory()->create(['user_type' => UserTypeEnum::TEACHER, 'name' => 'Bob']);

        $response = $this->getJson('/api/v1/teachers?sort_by=name&order=desc');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.name', 'Charlie')
            ->assertJsonPath('data.data.1.name', 'Bob')
            ->assertJsonPath('data.data.2.name', 'Alice');
    });

    it('can paginate teachers with custom per_page', function () {
        User::factory()->count(20)->create(['user_type' => UserTypeEnum::TEACHER]);

        $response = $this->getJson('/api/v1/teachers?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    });
});
