<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->nullable()->unique()->constrained('source_records')->nullOnDelete();
            $table->string('source_type', 80)->default('directory_source_candidate')->index();
            $table->string('name', 255);
            $table->text('url')->nullable();
            $table->string('normalized_domain', 255)->nullable()->index();
            $table->string('pref_code', 2)->nullable()->index();
            $table->string('pref_name', 50)->nullable()->index();
            $table->string('city', 100)->nullable()->index();
            $table->string('organization_type', 80)->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->string('crawl_status', 40)->default('not_crawled')->index();
            $table->timestamp('last_crawled_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->index(['pref_code', 'crawl_status']);
            $table->index(['status', 'crawl_status']);
        });

        Schema::create('directory_source_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_source_id')->constrained('directory_sources')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 40);
            $table->string('normalized_domain', 255)->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->string('link_text', 255)->nullable();
            $table->string('page_type', 80)->default('member_list_candidate')->index();
            $table->string('status', 40)->default('candidate')->index();
            $table->unsignedSmallInteger('score')->default(0)->index();
            $table->string('confidence', 40)->default('medium')->index();
            $table->text('discovered_from')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['directory_source_id', 'url_hash'], 'dir_source_page_unique');
            $table->index(['directory_source_id', 'score'], 'dir_source_page_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_source_pages');
        Schema::dropIfExists('directory_sources');
    }
};
