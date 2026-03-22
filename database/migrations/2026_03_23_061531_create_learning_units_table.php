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
        Schema::create('learning_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->string('title');
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
        Schema::dropIfExists('learning_units');
    }
};
