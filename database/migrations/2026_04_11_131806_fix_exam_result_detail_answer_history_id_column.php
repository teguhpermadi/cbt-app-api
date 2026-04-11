<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_result_detail_answer_history', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->ulid('id')->primary();
        });
    }

    public function down(): void
    {
        Schema::table('exam_result_detail_answer_history', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->id();
        });
    }
};
