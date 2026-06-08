<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReasonCodeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['type' => 'send_reason', 'code' => 'HP_STALE_BUT_ACTIVE', 'label' => '事業稼働・HP更新停止', 'description' => '事業は動いていそうだが、HPの更新が止まっている。', 'sort_order' => '1'],
            ['type' => 'send_reason', 'code' => 'UPDATE_TARGET_CLEAR', 'label' => '更新対象が明確', 'description' => 'お知らせ、施工事例、採用情報など、自走更新化できそうな更新対象が明確に存在する。', 'sort_order' => '2'],
            ['type' => 'send_reason', 'code' => 'CASE_STUDY_STOPPED', 'label' => '事例・実績が停止', 'description' => '施工事例、実績紹介、導入事例、相談事例、症例などが止まっている。', 'sort_order' => '3'],
            ['type' => 'send_reason', 'code' => 'NEWS_STOPPED', 'label' => 'お知らせ停止', 'description' => 'お知らせ、新着情報、休業案内などが止まっている。', 'sort_order' => '4'],
            ['type' => 'send_reason', 'code' => 'RECRUIT_PAGE_STALE', 'label' => '採用情報が古い', 'description' => '採用情報、募集要項、採用ページが古い、または外部求人との乖離がありそう。', 'sort_order' => '5'],
            ['type' => 'send_reason', 'code' => 'SELF_UPDATE_FIT_HIGH', 'label' => '自走更新化適性が高い', 'description' => 'HPの構造や業種特性から、自走更新化に向いていそう。', 'sort_order' => '6'],
            ['type' => 'send_reason', 'code' => 'CONTACT_METHOD_AVAILABLE', 'label' => '連絡手段あり', 'description' => 'メール、フォーム、電話など、営業到達可能な連絡手段がある。', 'sort_order' => '7'],
            ['type' => 'send_reason', 'code' => 'LOCAL_SME_FIT', 'label' => '地域中小企業に合う', 'description' => '地域中小企業として、TRUSTEPSの提案対象に合いそう。', 'sort_order' => '8'],
            ['type' => 'send_reason', 'code' => 'COST_REDUCTION_HOOK', 'label' => 'コスト削減フックあり', 'description' => '制作会社依存、保守費、外注更新費など、ランニングコスト削減の提案余地がありそう。', 'sort_order' => '9'],
            ['type' => 'send_reason', 'code' => 'PARTIAL_CMS_FIT', 'label' => '部分CMS化が合う', 'description' => 'HP全体ではなく、一部の更新領域だけをCMS化すれば価値がありそう。', 'sort_order' => '10'],
            ['type' => 'send_reason', 'code' => 'MOBILE_WEAKNESS_CLEAR', 'label' => 'モバイル弱点あり', 'description' => 'スマホ対応、表示崩れ、モバイル導線などの明確な弱点がある。', 'sort_order' => '11'],
            ['type' => 'send_reason', 'code' => 'DESIGN_OLD_BUT_BUSINESS_ACTIVE', 'label' => '古いが事業稼働', 'description' => 'デザインは古いが、事業は現在も稼働していそう。', 'sort_order' => '12'],
            ['type' => 'send_reason', 'code' => 'SNS_ACTIVE_HP_STALE', 'label' => 'SNS稼働・HP停止', 'description' => 'SNSは更新されているが、HPは止まっている。', 'sort_order' => '13'],
            ['type' => 'send_reason', 'code' => 'PORTAL_ACTIVE_HP_STALE', 'label' => '外部媒体稼働・HP停止', 'description' => '外部ポータル、求人媒体、Googleビジネス等は動いているが、HPは止まっている。', 'sort_order' => '14'],
            ['type' => 'no_reason', 'code' => 'HP_ALREADY_STRONG', 'label' => 'HPが強い', 'description' => 'HPがすでに十分整っており、更新も機能している。', 'sort_order' => '1'],
            ['type' => 'no_reason', 'code' => 'INDUSTRY_MISMATCH', 'label' => '業種不一致', 'description' => '対象業種として合わない、または本事業の初期仮説と大きく外れる。', 'sort_order' => '2'],
            ['type' => 'no_reason', 'code' => 'LARGE_CORP_OR_CHAIN', 'label' => '大手・チェーン', 'description' => '大手企業、全国チェーン、フランチャイズ本部など、初期対象外。', 'sort_order' => '3'],
            ['type' => 'no_reason', 'code' => 'WEB_IT_AD_COMPANY', 'label' => 'Web/IT/広告系', 'description' => 'Web制作会社、IT会社、広告代理店など、対象外または競合・専門領域。', 'sort_order' => '4'],
            ['type' => 'no_reason', 'code' => 'BUSINESS_INACTIVE', 'label' => '事業停止疑い', 'description' => '閉業、休業、事業停止、活動実態なしの可能性が高い。', 'sort_order' => '5'],
            ['type' => 'no_reason', 'code' => 'NO_CONTACT_METHOD', 'label' => '連絡手段なし', 'description' => 'メール、フォーム、電話などの連絡手段が見当たらない。', 'sort_order' => '6'],
            ['type' => 'no_reason', 'code' => 'PORTAL_DOMINANT', 'label' => 'ポータル依存強い', 'description' => '業界特化ポータルへの依存が強すぎ、自社HP改善の優先度が低そう。', 'sort_order' => '7'],
            ['type' => 'no_reason', 'code' => 'SNS_ONLY_BUT_OK', 'label' => 'SNS中心で成立', 'description' => 'SNS中心で十分成立しており、HP更新化の提案余地が薄い。', 'sort_order' => '8'],
            ['type' => 'no_reason', 'code' => 'COMPLEX_RESERVATION_REQUIRED', 'label' => '予約システム中核', 'description' => '予約システムが事業の中核で、初期MVPの範囲を超える。', 'sort_order' => '9'],
            ['type' => 'no_reason', 'code' => 'PAYMENT_POS_CRM_CORE', 'label' => '決済/POS/CRM中核', 'description' => '決済、POS、CRM、会員管理などが事業の中核で、初期MVPの範囲を超える。', 'sort_order' => '10'],
            ['type' => 'no_reason', 'code' => 'REAL_ESTATE_DB_CORE', 'label' => '不動産DB中核', 'description' => '不動産物件DB、物件検索、ポータル連携が中核で、初期MVPの範囲を超える。', 'sort_order' => '11'],
            ['type' => 'no_reason', 'code' => 'AUTOMOTIVE_INVENTORY_CORE', 'label' => '中古車在庫DB中核', 'description' => '中古車在庫DB、在庫検索、カーセンサー等との連携が中核で、初期MVPの範囲を超える。', 'sort_order' => '12'],
            ['type' => 'no_reason', 'code' => 'EC_CORE', 'label' => 'EC中核', 'description' => 'EC・オンライン販売が事業の中核で、初期MVPの範囲を超える。', 'sort_order' => '13'],
            ['type' => 'no_reason', 'code' => 'MEDICAL_REGULATION_RISK', 'label' => '医療規制リスク', 'description' => '医療広告ガイドライン等、法規制・表現規制のリスクが強い。', 'sort_order' => '14'],
            ['type' => 'no_reason', 'code' => 'LOW_BUDGET_SIGNAL', 'label' => '予算感が低そう', 'description' => '予算感がかなり低そう、または有償提案に耐えにくそう。', 'sort_order' => '15'],
            ['type' => 'no_reason', 'code' => 'LEGAL_OR_BRAND_RISK', 'label' => '法務・ブランドリスク', 'description' => '法務リスク、ブランド毀損リスク、営業接触リスクが高い。', 'sort_order' => '16'],
            ['type' => 'no_reason', 'code' => 'NOT_TARGET_AREA', 'label' => '対象エリア外', 'description' => '現在の調査対象・営業対象エリア外。', 'sort_order' => '17'],
            ['type' => 'no_reason', 'code' => 'DUPLICATE_RECORD', 'label' => '重複レコード', 'description' => '既存レコードと重複している。', 'sort_order' => '18'],
            ['type' => 'no_reason', 'code' => 'DOMAIN_EXPIRED', 'label' => 'ドメイン失効疑い', 'description' => 'ドメイン失効、サイト消滅、パーキングページ化などが疑われる。', 'sort_order' => '19'],
            ['type' => 'hold_reason', 'code' => 'NEED_MANUAL_REVIEW', 'label' => '目視確認が必要', 'description' => 'もう少し目視確認が必要。', 'sort_order' => '1'],
            ['type' => 'hold_reason', 'code' => 'POSSIBLE_DUPLICATE', 'label' => '重複可能性', 'description' => '重複の可能性があるが、まだ確定できない。', 'sort_order' => '2'],
            ['type' => 'hold_reason', 'code' => 'UNCLEAR_INDUSTRY', 'label' => '業種不明瞭', 'description' => '業種分類が曖昧。', 'sort_order' => '3'],
            ['type' => 'hold_reason', 'code' => 'UNCLEAR_BUSINESS_ACTIVE', 'label' => '事業稼働不明', 'description' => '事業が現在も動いているか不明。', 'sort_order' => '4'],
            ['type' => 'hold_reason', 'code' => 'SITE_TEMPORARILY_DOWN', 'label' => '一時的サイト不通', 'description' => '一時的にサイトが落ちている可能性がある。', 'sort_order' => '5'],
            ['type' => 'hold_reason', 'code' => 'CONTACT_UNCLEAR', 'label' => '連絡手段不明', 'description' => '連絡手段があるか、どれを使うべきか不明。', 'sort_order' => '6'],
            ['type' => 'hold_reason', 'code' => 'GOOD_BUT_NOT_INITIAL_INDUSTRY', 'label' => '良いが初期業種外', 'description' => '良さそうだが、初期の重点業種・初期アドオン対象ではない。', 'sort_order' => '7'],
            ['type' => 'hold_reason', 'code' => 'NEED_SECOND_SNAPSHOT', 'label' => '再取得確認したい', 'description' => '再クロール・再スクリーンショットで確認したい。', 'sort_order' => '8'],
            ['type' => 'hold_reason', 'code' => 'NEED_MORE_DATA', 'label' => '情報不足', 'description' => '情報が足りず、現時点では判断しきれない。', 'sort_order' => '9'],
            ['type' => 'hold_reason', 'code' => 'CLASSIFICATION_UNCERTAIN', 'label' => '分類自信なし', 'description' => '分類または判定に自信がない。', 'sort_order' => '10'],
        ];

        foreach ($rows as $row) {
            DB::table('reason_codes')->updateOrInsert(
                ['type' => $row['type'], 'code' => $row['code']],
                [
                    'label' => $row['label'],
                    'description' => $row['description'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
