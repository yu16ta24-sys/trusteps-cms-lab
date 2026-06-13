<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->boolean('url_dead')->default(false)->after('hp_snapshot_id');
        });

        // rank列をvarchar(1)→varchar(20)に拡張（'url_dead'等の値を保持するため）
        DB::statement('ALTER TABLE company_score_summaries MODIFY rank VARCHAR(20) NULL');
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->dropColumn('url_dead');
        });

        DB::statement('ALTER TABLE company_score_summaries MODIFY rank VARCHAR(1) NULL');
    }
};
