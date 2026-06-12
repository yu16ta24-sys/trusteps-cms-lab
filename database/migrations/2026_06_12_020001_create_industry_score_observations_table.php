<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_score_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('industries')->cascadeOnDelete();
            $table->string('axis_key', 60);
            $table->tinyInteger('value');
            $table->foreignId('source_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('prefecture_id')->nullable()->constrained('prefectures')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['industry_id', 'axis_key']);
            $table->index('source_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_score_observations');
    }
};
