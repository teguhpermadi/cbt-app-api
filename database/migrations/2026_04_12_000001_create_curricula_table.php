<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('curriculum_type', 50);
            $table->text('description')->nullable();
            $table->string('phase', 50)->nullable();
            $table->string('level', 50)->nullable();
            $table->json('grade_range')->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->json('subjects')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('curriculum_type');
            $table->index('phase');
            $table->index('level');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
