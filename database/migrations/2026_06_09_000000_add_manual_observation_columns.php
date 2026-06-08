<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hp_snapshots', 'observation_note')) {
            Schema::table('hp_snapshots', function (Blueprint $table) {
                $table->text('observation_note')->nullable()->after('error_message');
            });
        }

        if (!Schema::hasColumn('hp_facts', 'portal_dependency_level')) {
            Schema::table('hp_facts', function (Blueprint $table) {
                $table->string('portal_dependency_level', 50)->nullable()->index()->after('footer_year_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hp_facts', 'portal_dependency_level')) {
            Schema::table('hp_facts', function (Blueprint $table) {
                $table->dropColumn('portal_dependency_level');
            });
        }

        if (Schema::hasColumn('hp_snapshots', 'observation_note')) {
            Schema::table('hp_snapshots', function (Blueprint $table) {
                $table->dropColumn('observation_note');
            });
        }
    }
};
