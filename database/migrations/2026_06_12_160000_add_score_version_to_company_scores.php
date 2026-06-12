<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_scores', function (Blueprint $table) {
            $table->string('score_version', 40)->default('scoring_v1.0')->after('algo_version')->index();
        });
    }

    public function down(): void
    {
        Schema::table('company_scores', function (Blueprint $table) {
            $table->dropColumn('score_version');
        });
    }
};
