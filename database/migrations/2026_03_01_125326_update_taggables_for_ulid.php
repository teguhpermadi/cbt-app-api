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
        Schema::table('taggables', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->dropUnique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->dropIndex('taggables_taggable_type_taggable_id_index');
        });

        Schema::table('taggables', function (Blueprint $table) {
            $table->char('taggable_id', 26)->change();
        });

        Schema::table('taggables', function (Blueprint $table) {
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id'], 'taggables_taggable_type_taggable_id_index');
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taggables', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->dropUnique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->dropIndex('taggables_taggable_type_taggable_id_index');
        });

        Schema::table('taggables', function (Blueprint $table) {
            $table->unsignedBigInteger('taggable_id')->change();
        });

        Schema::table('taggables', function (Blueprint $table) {
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id'], 'taggables_taggable_type_taggable_id_index');
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }
};
