<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_reviewers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('state')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->integer('suggested_questions_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_reviewers');
    }
};
