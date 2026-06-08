<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | L0: Master / Dimension Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('prefectures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique()->comment('JIS prefecture code');
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('prefecture_scale', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prefecture_id')->constrained('prefectures')->restrictOnDelete();
            $table->string('code', 6)->unique()->comment('JIS municipality code');
            $table->string('name');
            $table->string('municipality_scale', 50)->nullable()->index();
            $table->string('population_band', 50)->nullable()->index();
            $table->string('city_type', 50)->nullable()->index();
            $table->boolean('is_prefectural_capital')->default(false);
            $table->boolean('is_designated_city')->default(false);
            $table->boolean('is_core_city')->default(false);
            $table->boolean('is_remote_area')->default(false);
            $table->timestamps();

            $table->index(['prefecture_id', 'municipality_scale']);
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('update_targets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index()->comment('send_reason / no_reason / hold_reason');
            $table->string('code', 80);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'code']);
        });

        /*
        |--------------------------------------------------------------------------
        | L1: Raw Source Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 80)->index();
            $table->text('source_url')->nullable();
            $table->json('raw_json')->nullable()->comment('Immutable raw source payload');
            $table->string('corporate_number', 13)->nullable()->index();
            $table->string('normalized_domain', 255)->nullable()->index();
            $table->string('normalized_phone', 32)->nullable()->index();
            $table->string('name_norm', 255)->nullable()->index();
            $table->string('pref', 50)->nullable()->index();
            $table->string('city', 100)->nullable()->index();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['pref', 'city']);
        });

        /*
        |--------------------------------------------------------------------------
        | L2: Entity Resolution Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['candidate', 'confirmed', 'merged'])->default('candidate')->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->unsignedBigInteger('primary_domain_id')->nullable()->index();
            $table->string('legal_name', 255)->nullable();
            $table->string('display_name', 255);
            $table->string('name_norm', 255)->nullable()->index();
            $table->json('alias_names_json')->nullable();
            $table->string('corporate_number', 13)->nullable()->index();
            $table->string('pref', 50)->nullable()->index();
            $table->string('city', 100)->nullable()->index();
            $table->boolean('is_killed')->default(false)->index();
            $table->timestamp('merged_at')->nullable();
            $table->string('merged_by', 100)->nullable();
            $table->text('merge_reason')->nullable();
            $table->timestamps();

            $table->index(['municipality_id', 'industry_id']);
            $table->index(['status', 'is_killed']);
        });

        Schema::create('company_source_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('source_record_id')->unique()->constrained('source_records')->cascadeOnDelete();
            $table->string('match_type', 50)->index();
            $table->decimal('match_confidence', 3, 2)->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'match_type']);
        });

        Schema::create('resolution_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->constrained('source_records')->cascadeOnDelete();
            $table->foreignId('candidate_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->enum('decision', ['same', 'different', 'unsure'])->index();
            $table->json('signals_json')->nullable();
            $table->text('reason')->nullable();
            $table->string('algo_version', 20)->default('v1')->index();
            $table->string('decided_by', 100)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['source_record_id', 'candidate_company_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | L3: Web Asset / Evidence Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('url');
            $table->string('normalized_domain', 255)->nullable()->index();
            $table->string('role', 50)->default('official')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_portal')->default(false)->index();
            $table->timestamps();

            $table->index(['company_id', 'is_primary']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('primary_domain_id', 'companies_primary_domain_id_foreign')
                ->references('id')
                ->on('domains')
                ->nullOnDelete();
        });

        Schema::create('hp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->enum('crawl_type', ['initial', 'error_retry', 'manual_verify'])->default('initial')->index();
            $table->string('snapshot_version', 20)->default('v1');
            $table->text('requested_url')->nullable();
            $table->text('final_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->string('raw_html_path')->nullable();
            $table->string('text_path')->nullable();
            $table->string('screenshot_pc_path')->nullable();
            $table->string('screenshot_sp_path')->nullable();
            $table->string('meta_json_path')->nullable();
            $table->string('error_type', 50)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('crawled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['domain_id', 'crawled_at']);
        });

        /*
        |--------------------------------------------------------------------------
        | L4: Observed Facts Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('hp_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hp_snapshot_id')->unique()->constrained('hp_snapshots')->cascadeOnDelete();
            $table->boolean('has_ec')->nullable();
            $table->boolean('has_reservation')->nullable();
            $table->boolean('has_recruiting')->nullable();
            $table->boolean('has_portal_link')->nullable();
            $table->boolean('has_sns_link')->nullable();
            $table->boolean('has_google_business_link')->nullable();
            $table->boolean('has_contact_form')->nullable();
            $table->boolean('has_public_email')->nullable();
            $table->boolean('has_phone')->nullable();
            $table->string('contact_method_type', 50)->nullable()->index();
            $table->string('update_status', 50)->nullable()->index();
            $table->boolean('has_update_targets')->nullable();
            $table->string('cms_type', 50)->nullable()->index();
            $table->string('builder_type', 50)->nullable()->index();
            $table->boolean('mobile_friendly')->nullable();
            $table->boolean('ssl_enabled')->nullable();
            $table->string('footer_year_status', 50)->nullable()->index();
            $table->string('extractor_version', 30)->default('v1');
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('snapshot_update_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hp_snapshot_id')->constrained('hp_snapshots')->cascadeOnDelete();
            $table->foreignId('update_target_id')->constrained('update_targets')->restrictOnDelete();
            $table->boolean('is_present')->nullable();
            $table->boolean('is_stopped')->nullable();
            $table->date('last_update_date')->nullable();
            $table->json('evidence_json')->nullable();
            $table->string('extractor_version', 30)->default('v1');
            $table->timestamps();

            $table->unique(['hp_snapshot_id', 'update_target_id'], 'snapshot_update_target_unique');
            $table->index(['update_target_id', 'is_present', 'is_stopped'], 'sut_target_present_stopped_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | L5: Human Evaluation Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('company_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('axis', ['hp_weakness', 'self_update_fit', 'dev_difficulty', 'portal_dependence'])->index();
            $table->unsignedTinyInteger('value');
            $table->decimal('confidence', 2, 1);
            $table->unsignedTinyInteger('auto_suggested_value')->nullable();
            $table->string('algo_version', 20)->default('v1')->index();
            $table->json('reason_json')->nullable();
            $table->string('scored_by', 100)->nullable();
            $table->timestamp('scored_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'axis', 'scored_at']);
        });

        Schema::create('company_kill_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('flag', 50)->index();
            $table->text('note')->nullable();
            $table->string('source', 50)->nullable()->index();
            $table->string('flagged_by', 100)->nullable();
            $table->timestamp('flagged_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['company_id', 'flag']);
        });

        /*
        |--------------------------------------------------------------------------
        | L6: Human Judgment Layer
        |--------------------------------------------------------------------------
        */

        Schema::create('judgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('decision', ['send', 'no', 'hold'])->index();
            $table->json('context_json')->nullable();
            $table->string('algo_version', 20)->default('v1')->index();
            $table->string('decided_by', 100)->nullable();
            $table->timestamp('decided_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'decided_at']);
        });

        Schema::create('judgment_reason_links', function (Blueprint $table) {
            $table->foreignId('judgment_id')->constrained('judgments')->cascadeOnDelete();
            $table->foreignId('reason_code_id')->constrained('reason_codes')->cascadeOnDelete();
            $table->primary(['judgment_id', 'reason_code_id'], 'judgment_reason_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judgment_reason_links');
        Schema::dropIfExists('judgments');
        Schema::dropIfExists('company_kill_flags');
        Schema::dropIfExists('company_scores');
        Schema::dropIfExists('snapshot_update_targets');
        Schema::dropIfExists('hp_facts');
        Schema::dropIfExists('hp_snapshots');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign('companies_primary_domain_id_foreign');
        });

        Schema::dropIfExists('domains');
        Schema::dropIfExists('resolution_decisions');
        Schema::dropIfExists('company_source_links');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('source_records');
        Schema::dropIfExists('reason_codes');
        Schema::dropIfExists('update_targets');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('prefectures');
    }
};
