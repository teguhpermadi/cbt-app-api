<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->unsignedInteger('order')->default(0)->after('description');
            $table->unique(['subject_id', 'classroom_id', 'order'], 'learning_paths_subject_classroom_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropUnique('learning_paths_subject_classroom_order_unique');
            $table->dropColumn('order');
        });
    }
};
