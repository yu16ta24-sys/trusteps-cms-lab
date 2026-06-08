<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('companies', 'hp_observation_json')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('hp_observation_json')->nullable()->after('merge_reason');
            });
        }

        if (!Schema::hasColumn('companies', 'hp_observation_note')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->text('hp_observation_note')->nullable()->after('hp_observation_json');
            });
        }

        if (!Schema::hasColumn('companies', 'hp_observed_at')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->timestamp('hp_observed_at')->nullable()->index()->after('hp_observation_note');
            });
        }

        if (!Schema::hasColumn('companies', 'hp_observed_by')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('hp_observed_by', 100)->nullable()->after('hp_observed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'hp_observed_by')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('hp_observed_by');
            });
        }

        if (Schema::hasColumn('companies', 'hp_observed_at')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('hp_observed_at');
            });
        }

        if (Schema::hasColumn('companies', 'hp_observation_note')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('hp_observation_note');
            });
        }

        if (Schema::hasColumn('companies', 'hp_observation_json')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('hp_observation_json');
            });
        }
    }
};
