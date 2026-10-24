<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
        });

        // fill the Arabic name for categories that already exist
        $names = [
            'motivation' => 'تحفيز', 'wisdom' => 'حكمة', 'love' => 'حب', 'success' => 'نجاح', 'life' => 'حياة',
            'friendship' => 'صداقة', 'faith' => 'إيمان', 'books' => 'كتب', 'humor' => 'فكاهة',
        ];
        foreach ($names as $slug => $ar) {
            DB::table('categories')->where('slug', $slug)->update(['name_ar' => $ar]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });
    }
};
