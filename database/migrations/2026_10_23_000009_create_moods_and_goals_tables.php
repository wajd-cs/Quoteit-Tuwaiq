<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moods', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ar');
            $table->string('icon', 16)->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ar');
            $table->string('icon', 16)->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        // fixed lists, so they are added here and exist right after `php artisan migrate`
        $now = now();
        $moods = [
            ['happy', 'Happy', 'سعيد'],
            ['sad', 'Sad', 'حزين'],
            ['anxious', 'Anxious', 'متوتر'],
            ['unmotivated', 'Lost my spark', 'فاقد للشغف'],
            ['excited', 'Excited', 'متحمس'],
            ['calm', 'Calm', 'هادئ'],
        ];
        $goals = [
            ['motivation', 'Motivation', 'التحفيز'],
            ['comfort', 'Comfort', 'المواساة'],
            ['hope', 'Hope', 'الأمل'],
            ['peace', 'Peace of mind', 'الهدوء'],
            ['confidence', 'Self-confidence', 'الثقة بالنفس'],
        ];

        DB::table('moods')->insert(collect($moods)->map(fn ($m, $i) => [
            'slug' => $m[0], 'name' => $m[1], 'name_ar' => $m[2], 'sort' => $i, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
        DB::table('goals')->insert(collect($goals)->map(fn ($g, $i) => [
            'slug' => $g[0], 'name' => $g[1], 'name_ar' => $g[2], 'sort' => $i, 'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::dropIfExists('moods');
    }
};
