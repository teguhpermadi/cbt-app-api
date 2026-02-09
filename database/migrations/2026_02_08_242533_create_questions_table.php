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
        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('reading_material_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('difficulty');
            $table->integer('timer')->default(0);
            $table->text('content');
            $table->integer('score')->default(0);
            $table->text('hint')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
