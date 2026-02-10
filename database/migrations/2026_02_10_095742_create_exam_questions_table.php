<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained()->cascadeOnDelete();
            $table->integer('question_number');
            $table->text('content');
            $table->json('options');
            $table->json('key_answer');
            $table->integer('score_value');
            $table->string('question_type');
            $table->string('difficulty_level');
            $table->string('media_path')->nullable();
            $table->text('hint')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'question_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
