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
        Schema::create('exams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->integer('duration');
            $table->string('token');
            $table->boolean('is_token_visible');
            $table->boolean('is_published');
            $table->boolean('is_randomized_question');
            $table->boolean('is_randomized_answer');
            $table->boolean('is_show_result');
            $table->boolean('is_visible_hint');
            $table->integer('max_attempts')->nullable();
            $table->string('timer_type');
            $table->integer('passing_score');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
