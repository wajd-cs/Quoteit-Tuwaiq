<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // a draft has no publish time and only its owner sees it
            $table->boolean('is_draft')->default(false)->after('published_at');
            // font, size, colour and alignment chosen when writing the quote
            $table->json('style')->nullable()->after('is_draft');
        });

        Schema::create('quote_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('lang', 2);
            $table->text('text');
            $table->timestamps();
            $table->unique(['quote_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_translations');
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['is_draft', 'style']);
        });
    }
};
