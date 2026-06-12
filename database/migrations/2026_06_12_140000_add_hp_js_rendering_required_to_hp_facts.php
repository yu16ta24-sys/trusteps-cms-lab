<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->boolean('hp_js_rendering_required')->default(false)->after('extracted_at');
        });
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->dropColumn('hp_js_rendering_required');
        });
    }
};
