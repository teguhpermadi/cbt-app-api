<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_units', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('xp_reward');
        });

        Schema::table('learning_lessons', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('xp_reward');
        });
    }

    public function down(): void
    {
        Schema::table('learning_units', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });

        Schema::table('learning_lessons', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
};
