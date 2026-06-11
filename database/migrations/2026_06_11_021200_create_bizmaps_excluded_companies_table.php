<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bizmaps_excluded_companies')) {
            Schema::create('bizmaps_excluded_companies', function (Blueprint $table) {
                $table->id();
                $table->string('detail_url', 2048)->unique()->comment('BIZMAPSの詳細ページURL（会社の一意キー）');
                $table->string('name')->nullable()->comment('会社名');
                $table->string('pref', 20)->nullable()->comment('都道府県');
                $table->string('city', 50)->nullable()->comment('市区町村');
                $table->timestamp('excluded_at')->useCurrent();
                $table->timestamps();

                $table->index('pref', 'bec_pref_idx');
                $table->index('city', 'bec_city_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bizmaps_excluded_companies');
    }
};
