<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // moods a quote fits
        Schema::create('mood_quote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mood_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unique(['mood_id', 'quote_id']);
        });

        // goals a quote helps with
        Schema::create('goal_quote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unique(['goal_id', 'quote_id']);
        });

        // categories that usually help with a goal (used for quotes that have no tags)
        Schema::create('category_goal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->unique(['category_id', 'goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_goal');
        Schema::dropIfExists('goal_quote');
        Schema::dropIfExists('mood_quote');
    }
};
