<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            if (!Schema::hasColumn('hp_facts', 'hp_title')) {
                $table->string('hp_title', 500)->nullable()->after('portal_dependency_level');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_description')) {
                $table->string('hp_description', 1000)->nullable()->after('hp_title');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_last_modified')) {
                $table->string('hp_last_modified', 100)->nullable()->after('hp_description');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_news')) {
                $table->boolean('hp_has_news')->nullable()->after('hp_last_modified');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_latest_post_date')) {
                $table->string('hp_latest_post_date', 50)->nullable()->after('hp_has_news');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_update_staleness_days')) {
                $table->integer('hp_update_staleness_days')->nullable()->after('hp_latest_post_date');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_page_count')) {
                $table->integer('hp_page_count')->nullable()->after('hp_update_staleness_days');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_map')) {
                $table->boolean('hp_has_map')->nullable()->after('hp_page_count');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_image_count')) {
                $table->integer('hp_image_count')->nullable()->after('hp_has_map');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_word_count')) {
                $table->integer('hp_word_count')->nullable()->after('hp_image_count');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_tabelog')) {
                $table->boolean('hp_has_tabelog')->nullable()->after('hp_word_count');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_hotpepper')) {
                $table->boolean('hp_has_hotpepper')->nullable()->after('hp_has_tabelog');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_jalan')) {
                $table->boolean('hp_has_jalan')->nullable()->after('hp_has_hotpepper');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_has_suumo')) {
                $table->boolean('hp_has_suumo')->nullable()->after('hp_has_jalan');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_portal_links')) {
                $table->json('hp_portal_links')->nullable()->after('hp_has_suumo');
            }
            if (!Schema::hasColumn('hp_facts', 'hp_improvement_score')) {
                $table->integer('hp_improvement_score')->nullable()->after('hp_portal_links');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $columns = [
                'hp_title', 'hp_description', 'hp_last_modified',
                'hp_has_news', 'hp_latest_post_date', 'hp_update_staleness_days',
                'hp_page_count', 'hp_has_map', 'hp_image_count', 'hp_word_count',
                'hp_has_tabelog', 'hp_has_hotpepper', 'hp_has_jalan', 'hp_has_suumo',
                'hp_portal_links', 'hp_improvement_score',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('hp_facts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
