<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('directory_source_pages')) {
            Schema::table('directory_source_pages', function (Blueprint $table) {
                if (! Schema::hasColumn('directory_source_pages', 'type_score')) {
                    $table->unsignedTinyInteger('type_score')->default(0)->index()->after('score');
                }
                if (! Schema::hasColumn('directory_source_pages', 'extraction_status')) {
                    $table->string('extraction_status', 20)->default('pending')->index()->after('status');
                }
                if (! Schema::hasColumn('directory_source_pages', 'last_fetched_at')) {
                    $table->timestamp('last_fetched_at')->nullable()->index()->after('last_seen_at');
                }
                if (! Schema::hasColumn('directory_source_pages', 'fetch_error')) {
                    $table->text('fetch_error')->nullable()->after('last_fetched_at');
                }
            });
        }

        if (! Schema::hasTable('extracted_business_candidates')) {
            Schema::create('extracted_business_candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('directory_source_page_id')->constrained('directory_source_pages')->cascadeOnDelete();
                $table->foreignId('directory_source_id')->constrained('directory_sources')->cascadeOnDelete();
                $table->string('business_name', 255)->nullable()->index();
                $table->string('business_name_kana', 255)->nullable();
                $table->text('address')->nullable();
                $table->string('tel', 50)->nullable()->index();
                $table->string('fax', 50)->nullable();
                $table->string('business_type', 100)->nullable()->index();
                $table->text('description')->nullable();
                $table->text('url_candidate')->nullable();
                $table->string('url_hash', 40)->nullable()->index();
                $table->string('normalized_domain', 255)->nullable()->index();
                $table->string('url_type', 30)->nullable()->index();
                $table->unsignedTinyInteger('url_confidence')->default(0)->index();
                $table->json('sns_urls')->nullable();
                $table->text('detail_page_url')->nullable();
                $table->text('raw_html_block')->nullable();
                $table->string('extraction_method', 50)->nullable()->index();
                $table->string('save_status', 20)->default('pending')->index();
                $table->foreignId('source_record_id')->nullable()->constrained('source_records')->nullOnDelete();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->index(['directory_source_page_id', 'save_status'], 'ebc_page_status_idx');
                $table->index(['directory_source_id', 'save_status'], 'ebc_source_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_business_candidates');

        if (Schema::hasTable('directory_source_pages')) {
            Schema::table('directory_source_pages', function (Blueprint $table) {
                foreach (['fetch_error', 'last_fetched_at', 'extraction_status', 'type_score'] as $column) {
                    if (Schema::hasColumn('directory_source_pages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
