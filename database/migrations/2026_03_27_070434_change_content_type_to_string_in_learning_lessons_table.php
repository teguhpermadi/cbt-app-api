<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_lessons', function (Blueprint $table) {
            $table->string('content_type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_lessons', function (Blueprint $table) {
            $table->enum('content_type', ['reading', 'video', 'web_link', 'quiz'])->change();
        });
    }
};
