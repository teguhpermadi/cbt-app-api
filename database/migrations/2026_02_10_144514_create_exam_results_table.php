<?php

use App\Enums\ExamResultTypeEnum;
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
        Schema::create('exam_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('exam_session_id')->constrained()->cascadeOnDelete();
            $table->float('total_score')->default(0);
            $table->float('score_percent')->default(0);
            $table->boolean('is_passed')->default(false);
            $table->string('result_type')->default(ExamResultTypeEnum::OFFICIAL);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
