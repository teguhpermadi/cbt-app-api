<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE exam_result_detail_answer_history RENAME TO exam_result_detail_answer_history_old');

            Schema::create('exam_result_detail_answer_history', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('exam_result_detail_id');
                $table->json('previous_answer')->nullable();
                $table->json('new_answer')->nullable();
                $table->ulid('edited_by')->nullable();
                $table->string('edit_reason')->nullable();
                $table->timestamps();

                $table->foreign('exam_result_detail_id')
                    ->references('id')
                    ->on('exam_result_details')
                    ->cascadeOnDelete();
            });

            DB::statement('INSERT INTO exam_result_detail_answer_history (id, exam_result_detail_id, previous_answer, new_answer, edited_by, edit_reason, created_at, updated_at)
                SELECT id, exam_result_detail_id, previous_answer, new_answer, edited_by, edit_reason, created_at, updated_at
                FROM exam_result_detail_answer_history_old');

            DB::statement('DROP TABLE exam_result_detail_answer_history_old');

            return;
        }

        Schema::table('exam_result_detail_answer_history', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->ulid('id')->primary();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE exam_result_detail_answer_history RENAME TO exam_result_detail_answer_history_old');

            Schema::create('exam_result_detail_answer_history', function (Blueprint $table) {
                $table->id();
                $table->ulid('exam_result_detail_id');
                $table->json('previous_answer')->nullable();
                $table->json('new_answer')->nullable();
                $table->ulid('edited_by')->nullable();
                $table->string('edit_reason')->nullable();
                $table->timestamps();

                $table->foreign('exam_result_detail_id')
                    ->references('id')
                    ->on('exam_result_details')
                    ->cascadeOnDelete();
            });

            DB::statement('INSERT INTO exam_result_detail_answer_history (id, exam_result_detail_id, previous_answer, new_answer, edited_by, edit_reason, created_at, updated_at)
                SELECT id, exam_result_detail_id, previous_answer, new_answer, edited_by, edit_reason, created_at, updated_at
                FROM exam_result_detail_answer_history_old');

            DB::statement('DROP TABLE exam_result_detail_answer_history_old');

            return;
        }

        Schema::table('exam_result_detail_answer_history', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->id();
        });
    }
};
