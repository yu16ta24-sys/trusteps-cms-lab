<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_score_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete()->index();
            $table->decimal('total_score', 3, 2)->nullable();
            $table->string('rank', 1)->nullable();
            $table->string('candidate_type', 40)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('flags_json')->nullable();
            $table->json('caps_applied_json')->nullable();
            $table->text('reason_summary')->nullable();
            $table->string('score_version', 40)->default('scoring_v1.0')->index();
            $table->timestamps();

            $table->unique(['company_id', 'score_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_score_summaries');
    }
};
