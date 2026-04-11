<?php

declare(strict_types=1);

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
        Schema::create('exam_result_detail_answer_history', function (Blueprint $table) {
            $table->id();
            $table->ulid('exam_result_detail_id')->constrained('exam_result_details')->cascadeOnDelete();
            $table->json('previous_answer')->nullable();
            $table->json('new_answer')->nullable();
            $table->ulid('edited_by')->nullable();
            $table->string('edit_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_result_detail_answer_history');
    }
};
