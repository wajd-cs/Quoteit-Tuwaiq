<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('contact_link');
            $table->string('avatar_path')->nullable()->after('locale');
            // accessibility settings (contrast, colour mode, text size, reading speed ...)
            $table->json('preferences')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'avatar_path', 'preferences']);
        });
    }
};
