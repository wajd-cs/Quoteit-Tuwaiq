<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // off until the user turns it on in "Edit profile"
            $table->boolean('notify_new_quotes')->default(false)->after('preferences');
            $table->timestamp('notifications_seen_at')->nullable()->after('notify_new_quotes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_new_quotes', 'notifications_seen_at']);
        });
    }
};
