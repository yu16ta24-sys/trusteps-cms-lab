<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateTargetSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code' => 'news', 'name' => 'お知らせ', 'sort_order' => '1', 'notes' => '会社からの告知、休業案内、営業時間変更、新着情報、重要なお知らせなど。'],
            ['code' => 'case_study', 'name' => '施工事例・実績紹介', 'sort_order' => '2', 'notes' => '施工事例、導入事例、制作実績、相談事例、解決実績、症例、ビフォーアフターなど。'],
            ['code' => 'works_gallery', 'name' => '写真ギャラリー・制作実績', 'sort_order' => '3', 'notes' => '写真中心のギャラリー、作品集、施設写真、施工写真、商品写真、ビフォーアフター画像集など。'],
            ['code' => 'blog', 'name' => 'ブログ・コラム', 'sort_order' => '4', 'notes' => 'スタッフブログ、専門コラム、日記、読み物、ノウハウ記事、症状解説、豆知識など。'],
            ['code' => 'faq', 'name' => 'FAQ・よくある質問', 'sort_order' => '5', 'notes' => 'よくある質問、Q&A、利用前の疑問、料金や流れに関する質問など。'],
            ['code' => 'voice', 'name' => 'お客様の声・利用者の声', 'sort_order' => '6', 'notes' => 'お客様の声、利用者の声、患者の声、保護者の声、レビュー紹介など。'],
            ['code' => 'staff', 'name' => 'スタッフ紹介・職人紹介・先生紹介', 'sort_order' => '7', 'notes' => 'スタッフ、職人、先生、担当者、代表者、チーム紹介など。'],
            ['code' => 'recruit', 'name' => '採用情報', 'sort_order' => '8', 'notes' => '求人情報、採用ページ、募集職種、採用メッセージ、職場紹介、採用ブログなど。'],
            ['code' => 'event', 'name' => 'イベント・見学会・セミナー', 'sort_order' => '9', 'notes' => 'イベント情報、見学会、内覧会、セミナー、説明会、体験会、相談会、展示会など。'],
            ['code' => 'campaign', 'name' => 'キャンペーン・フェア情報', 'sort_order' => '10', 'notes' => 'キャンペーン、フェア、期間限定企画、特典、割引、季節企画など。'],
            ['code' => 'menu_price', 'name' => 'メニュー・料金表', 'sort_order' => '11', 'notes' => 'メニュー、料金表、価格表、サービス料金、施術メニュー、プラン料金など。'],
            ['code' => 'area', 'name' => '対応エリア', 'sort_order' => '12', 'notes' => '対応地域、施工対応エリア、訪問可能範囲、営業エリアなど。'],
            ['code' => 'schedule', 'name' => '営業日・休業日・出店予定・スケジュール', 'sort_order' => '13', 'notes' => '営業日、休業日、臨時休業、出店予定、開催スケジュールなど。予約枠管理やシフト管理には踏み込まない。'],
            ['code' => 'facility_report', 'name' => '施設だより・園だより・活動報告', 'sort_order' => '14', 'notes' => '施設だより、園だより、活動報告、施設日記、行事報告、給食紹介、利用者向け報告など。'],
            ['code' => 'product_service', 'name' => '商品・サービス紹介', 'sort_order' => '15', 'notes' => '商品紹介、サービス紹介、提供メニュー、製品紹介、設備紹介、取り扱いサービスなど。'],
            ['code' => 'download', 'name' => '資料ダウンロード', 'sort_order' => '16', 'notes' => 'PDF資料、パンフレット、申込書、料金表、会社案内、採用資料など。'],
            ['code' => 'media_award', 'name' => 'メディア掲載・受賞実績', 'sort_order' => '17', 'notes' => 'メディア掲載、受賞、認定、表彰、資格取得、実績掲載など。'],
        ];

        foreach ($rows as $row) {
            DB::table('update_targets')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'notes' => $row['notes'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
