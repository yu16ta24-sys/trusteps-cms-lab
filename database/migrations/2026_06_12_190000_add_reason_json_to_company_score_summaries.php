<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_score_summaries', function (Blueprint $table) {
            $table->json('reason_json')->nullable()->after('reason_summary');
        });
    }

    public function down(): void
    {
        Schema::table('company_score_summaries', function (Blueprint $table) {
            $table->dropColumn('reason_json');
        });
    }
};
