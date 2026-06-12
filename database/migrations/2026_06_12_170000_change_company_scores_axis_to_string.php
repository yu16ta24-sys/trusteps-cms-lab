<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_scores.axis を enum(4値) から string(40) に変更する。
     * scoring_v1.0 の 5 軸（opportunity_score 等）を同テーブルに保存可能にするため。
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE company_scores MODIFY axis VARCHAR(40) NOT NULL");
        } else {
            Schema::table('company_scores', function ($table) {
                $table->string('axis', 40)->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE company_scores MODIFY axis "
                . "ENUM('hp_weakness','self_update_fit','dev_difficulty','portal_dependence') NOT NULL"
            );
        } else {
            Schema::table('company_scores', function ($table) {
                $table->string('axis')->change();
            });
        }
    }
};
