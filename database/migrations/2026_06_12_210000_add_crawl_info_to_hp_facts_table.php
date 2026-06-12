<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2階層クロール（Phase 2-B）の結果カラムを追加する。
     */
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->json('hp_crawled_subpages')->nullable()->after('hp_has_local_keyword');
            $table->unsignedTinyInteger('hp_subpage_count')->default(0)->after('hp_crawled_subpages');
        });
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->dropColumn(['hp_crawled_subpages', 'hp_subpage_count']);
        });
    }
};
