<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('industry_score_axes')) {
            Schema::create('industry_score_axes', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('label', 150);
                $table->text('description')->nullable();
                $table->string('category', 50)->index();
                $table->unsignedTinyInteger('min_value')->default(0);
                $table->unsignedTinyInteger('max_value')->default(5);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['category', 'sort_order'], 'isa_category_sort_idx');
            });
        }

        if (!Schema::hasTable('industry_scores')) {
            Schema::create('industry_scores', function (Blueprint $table) {
                $table->id();
                $table->string('industry_key', 50)->index();
                $table->string('axis_key', 100)->index();
                $table->unsignedTinyInteger('value')->nullable();
                $table->string('confidence', 20)->nullable()->index();
                $table->string('score_type', 20)->default('hypothesis')->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['industry_key', 'axis_key'], 'is_industry_axis_unique');
                $table->index(['industry_key', 'score_type'], 'is_industry_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_scores');
        Schema::dropIfExists('industry_score_axes');
    }
};
