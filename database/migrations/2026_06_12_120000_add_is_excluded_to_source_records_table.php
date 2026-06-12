<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_records', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('source_records', function (Blueprint $table) {
            $table->dropColumn('is_excluded');
        });
    }
};
