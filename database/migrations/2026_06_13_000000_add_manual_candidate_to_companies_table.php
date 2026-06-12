<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_manual_candidate')->default(false)->after('is_killed');
            $table->text('manual_candidate_reason')->nullable()->after('is_manual_candidate');
            $table->timestamp('manual_candidate_at')->nullable()->after('manual_candidate_reason');
            $table->string('manual_candidate_by')->nullable()->after('manual_candidate_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_manual_candidate', 'manual_candidate_reason', 'manual_candidate_at', 'manual_candidate_by']);
        });
    }
};
