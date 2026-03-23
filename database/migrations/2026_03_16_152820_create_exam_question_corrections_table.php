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
        Schema::create('exam_question_corrections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_question_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->integer('total_to_correct')->default(0);
            $table->integer('corrected_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'exam_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_question_corrections');
    }
};
