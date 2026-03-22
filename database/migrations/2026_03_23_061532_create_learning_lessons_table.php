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
        Schema::create('learning_lessons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->foreignUlid('question_bank_id')->nullable()->constrained('question_banks')->nullOnDelete();
            $table->string('title');
            $table->enum('content_type', ['reading', 'video', 'web_link', 'quiz']);
            $table->longText('content_data')->nullable(); // Can store text or URL
            $table->integer('order')->default(0);
            $table->integer('xp_reward')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_lessons');
    }
};
