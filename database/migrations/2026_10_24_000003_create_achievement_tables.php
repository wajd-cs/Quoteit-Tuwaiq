<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // badges a user has earned
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 40);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'badge']);
        });

        // daily quote challenges a user completed
        Schema::create('challenge_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('challenge', 40);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'day']);
        });

        // quote images made in the design studio
        Schema::create('quote_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('template', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_designs');
        Schema::dropIfExists('challenge_completions');
        Schema::dropIfExists('user_achievements');
    }
};
