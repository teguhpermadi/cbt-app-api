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
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->float('total_score')->default(0);
            $table->float('total_max_score')->default(0);
            $table->boolean('is_finished')->default(false);
            $table->boolean('is_corrected')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('finish_time')->nullable();
            $table->integer('duration_taken')->default(0);
            $table->string('ip_address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
