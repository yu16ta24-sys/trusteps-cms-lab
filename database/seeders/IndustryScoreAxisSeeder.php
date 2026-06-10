<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustryScoreAxisSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'key' => 'update_neta_score',
                'label' => '更新ネタ発生度',
                'description' => 'この業界に、日常的・季節的・事例的な更新ネタがどれくらい発生するか。',
                'category' => 'opportunity',
                'sort_order' => 10,
            ],
            [
                'key' => 'self_update_fit_score',
                'label' => '自走更新との相性',
                'description' => '顧客自身がCMSで更新する運用とどれくらい相性が良いか。',
                'category' => 'opportunity',
                'sort_order' => 20,
            ],
            [
                'key' => 'addon_fit_score',
                'label' => '業種アドオン適性',
                'description' => 'この業界専用の更新項目・テンプレ・入力UIを横展開しやすいか。',
                'category' => 'opportunity',
                'sort_order' => 30,
            ],
            [
                'key' => 'trust_hp_importance_score',
                'label' => '公式HPの信用重要度',
                'description' => '公式HPが信用形成・採用・問い合わせにどれくらい重要か。',
                'category' => 'opportunity',
                'sort_order' => 40,
            ],
            [
                'key' => 'existing_hp_maturity_score',
                'label' => '既存HP成熟度',
                'description' => '業界全体として、既存HPの完成度・スマホ対応・見た目がどれくらい成熟しているか。高いほど初期営業余白は減りやすい。',
                'category' => 'white_space',
                'sort_order' => 110,
            ],
            [
                'key' => 'wp_penetration_score',
                'label' => 'WordPress普及度',
                'description' => '業界内でWordPress等のCMSがどれくらい普及していそうか。高いほど「WP化しましょう」は刺さりにくい。',
                'category' => 'white_space',
                'sort_order' => 120,
            ],
            [
                'key' => 'active_update_rate_score',
                'label' => '更新運用成立度',
                'description' => '業界内でお知らせ・施工事例・採用などの重点更新対象が実際に動いている割合の仮説/実測。高いほど営業余白は減りやすい。',
                'category' => 'white_space',
                'sort_order' => 130,
            ],
            [
                'key' => 'market_white_space_score',
                'label' => '市場余白',
                'description' => 'TRUSTEPSの自走更新化・更新停止改善・部分CMS化の提案余地がどれくらい残っているか。',
                'category' => 'white_space',
                'sort_order' => 140,
            ],
            [
                'key' => 'sales_reachability_score',
                'label' => '営業到達性',
                'description' => '代表者・担当者へ提案が届きやすいか、問い合わせ導線や地域営業との相性が良いか。',
                'category' => 'execution',
                'sort_order' => 210,
            ],
            [
                'key' => 'portal_dependency_score',
                'label' => 'ポータル依存度',
                'description' => '食べログ・ホットペッパー・SUUMO・OTA等のポータル依存が強いか。高いほど公式HP単体提案は慎重に見る。',
                'category' => 'risk',
                'sort_order' => 310,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('industry_score_axes')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'label' => $row['label'],
                    'description' => $row['description'],
                    'category' => $row['category'],
                    'min_value' => 0,
                    'max_value' => 5,
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
