<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->string('subject_code', 50)->nullable();
            $table->string('type', 50);
            $table->string('difficulty', 20);
            $table->integer('timer')->default(30);
            $table->integer('score')->default(1);
            $table->text('content');
            $table->text('hint')->nullable();
            $table->string('taxonomy_type', 50)->nullable();
            $table->string('taxonomy_code', 50)->nullable();
            $table->longText('custom_material')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('generated_by', 100)->nullable();
            $table->longText('generation_prompt')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 36)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('curriculum_id');
            $table->index('subject_code');
            $table->index('type');
            $table->index('difficulty');
            $table->index('status');
            $table->index('taxonomy_type');
            $table->index('taxonomy_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_drafts');
    }
};
