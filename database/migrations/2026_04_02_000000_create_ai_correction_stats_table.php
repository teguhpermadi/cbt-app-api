<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_correction_stats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->string('batch_id')->nullable();
            $table->string('provider');
            $table->integer('total_jobs');
            $table->integer('completed_jobs')->default(0);
            $table->integer('failed_jobs')->default(0);
            $table->float('avg_time_per_job')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'status']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_correction_stats');
    }
};
