<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HP解析のコンテンツ検出結果カラムを追加する（全て boolean / default false）。
     */
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->boolean('hp_has_case_studies')->default(false)->after('hp_improvement_score');
            $table->boolean('hp_has_pricing')->default(false)->after('hp_has_case_studies');
            $table->boolean('hp_has_testimonials')->default(false)->after('hp_has_pricing');
            $table->boolean('hp_has_faq')->default(false)->after('hp_has_testimonials');
            $table->boolean('hp_has_staff_intro')->default(false)->after('hp_has_faq');
            $table->boolean('hp_has_recruit_page')->default(false)->after('hp_has_staff_intro');
            $table->boolean('hp_has_service_detail')->default(false)->after('hp_has_recruit_page');
            $table->boolean('hp_has_company_profile')->default(false)->after('hp_has_service_detail');
            $table->boolean('hp_has_owner_name')->default(false)->after('hp_has_company_profile');
            $table->boolean('hp_has_local_keyword')->default(false)->after('hp_has_owner_name');
        });
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->dropColumn([
                'hp_has_case_studies',
                'hp_has_pricing',
                'hp_has_testimonials',
                'hp_has_faq',
                'hp_has_staff_intro',
                'hp_has_recruit_page',
                'hp_has_service_detail',
                'hp_has_company_profile',
                'hp_has_owner_name',
                'hp_has_local_keyword',
            ]);
        });
    }
};
