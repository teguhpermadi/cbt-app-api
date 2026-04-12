<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy_type', 50);
            $table->string('category', 100);
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->json('subcategories')->nullable();
            $table->json('verbs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('taxonomy_type');
            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
