<?php

use App\Models\Question;
use App\Models\ReadingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a user', function () {
    $user = User::factory()->create();
    $readingMaterial = ReadingMaterial::factory()->create(['user_id' => $user->id]);

    expect($readingMaterial->user)->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

it('has many questions', function () {
    $readingMaterial = ReadingMaterial::factory()->create();
    Question::factory()->count(3)->create(['reading_material_id' => $readingMaterial->id]);

    expect($readingMaterial->questions)->toHaveCount(3);
    expect($readingMaterial->questions->first())->toBeInstanceOf(Question::class);
});

it('can be soft deleted', function () {
    $readingMaterial = ReadingMaterial::factory()->create();
    $readingMaterial->delete();

    $this->assertSoftDeleted($readingMaterial);
});
