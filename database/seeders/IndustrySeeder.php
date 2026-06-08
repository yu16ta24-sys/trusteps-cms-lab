<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['slug' => 'construction', 'name' => '建設・新築・リフォーム', 'sort_order' => '1', 'notes' => '新築、工務店、住宅建設、リフォーム。施工事例や実績紹介が中心になりやすく、更新停止している場合は自走更新化の余地がある。外構・塗装・設備はexterior_paint、不動産仲介・物件管理はreal_estate。'],
            ['slug' => 'exterior_paint', 'name' => '外構・塗装・設備', 'sort_order' => '2', 'notes' => '外構、塗装、屋根、電気、水道、空調、住宅設備系。施工前後のビフォーアフター、施工事例、対応エリア、FAQなど写真ベースの更新対象が多い可能性がある。'],
            ['slug' => 'professional', 'name' => '士業', 'sort_order' => '3', 'notes' => '税理士、司法書士、行政書士、社労士、弁護士、土地家屋調査士など。相談事例、FAQ、専門コラム、お知らせなどの更新対象があり得る。'],
            ['slug' => 'welfare_care', 'name' => '介護・福祉', 'sort_order' => '4', 'notes' => '介護施設、福祉施設、障害福祉、デイサービス、訪問介護など。施設の様子、イベント報告、採用情報、見学案内などの更新ニーズがある可能性が高い。'],
            ['slug' => 'manufacturing', 'name' => '製造業', 'sort_order' => '5', 'notes' => '自社で物理的な製品を製造している企業。企業信用、採用、BtoB取引の安心材料になりやすい。製品事例、設備紹介、採用情報、展示会出展、お知らせなどを観測する。'],
            ['slug' => 'btob_service', 'name' => 'BtoBサービス', 'sort_order' => '6', 'notes' => '清掃、警備、人材派遣、業務支援、印刷、IT、広告、法人向けサービスなど。企業信用、実績紹介、採用強化が主目的になりやすい。'],
            ['slug' => 'child_education', 'name' => '保育・教育', 'sort_order' => '7', 'notes' => '保育園、幼稚園、こども園、学習塾、予備校、子ども向け教育サービス。保護者向け安心感、活動報告、園だより、採用情報、イベント告知など。'],
            ['slug' => 'culture_event', 'name' => '教室・イベント・文化系', 'sort_order' => '8', 'notes' => '音楽教室、料理教室、ヨガ、ジム、カルチャースクール、イベント系、体験型サービスなど。スケジュールやイベント告知、活動報告、受講者の声など。'],
            ['slug' => 'local_service', 'name' => '地域生活サービス', 'sort_order' => '9', 'notes' => 'クリーニング、写真館、葬儀、家事代行、修理、生活密着型サービスなど。地域密着型でポータル依存が低い業種もあるが、更新頻度やHP重要度はばらつく。'],
            ['slug' => 'therapy', 'name' => '整体・整骨・接骨・鍼灸・治療院', 'sort_order' => '10', 'notes' => '整体、整骨、接骨、鍼灸、治療院など身体の不調改善を目的とする施術系。症状別解説、患者の声、ブログ、FAQなどの更新ニーズがあり得る。'],
            ['slug' => 'beauty', 'name' => '美容', 'sort_order' => '11', 'notes' => '美容室、エステ、脱毛、ネイル、まつ毛、リラクゼーションなど。ポータル依存や予約依存が強い可能性があるため、初期アドオンでは深追いしない候補。'],
            ['slug' => 'food', 'name' => '飲食', 'sort_order' => '12', 'notes' => '飲食店、カフェ、レストラン、居酒屋、テイクアウト店など。Instagram、食べログ、Googleビジネス、予約導線への依存を観測する。初期アドオンでは原則深追いしない。'],
            ['slug' => 'medical', 'name' => '医療・歯科', 'sort_order' => '13', 'notes' => '病院、クリニック、歯科医院など。医療広告ガイドライン、表現規制、予約システムが絡むため、初期アドオン開発対象からは外す。研究対象として分類・観測する。'],
            ['slug' => 'retail', 'name' => '小売', 'sort_order' => '14', 'notes' => '実店舗小売、専門店、販売店など。EC有無で性質が変わるが、v1では業種分割せずhas_ecで観測する。入荷情報、イベント情報、キャンペーンなど。'],
            ['slug' => 'lodging', 'name' => '宿泊', 'sort_order' => '15', 'notes' => 'ホテル、旅館、民宿、ゲストハウス、宿泊施設。OTA、自社予約エンジン、プラン管理などが絡みやすいため初期アドオンからは外す。'],
            ['slug' => 'real_estate', 'name' => '不動産', 'sort_order' => '16', 'notes' => '不動産売買、賃貸仲介、管理、物件紹介。物件DBやポータル依存が中核になりやすく、初期CMSアドオンには重いため隔離して観測する。'],
            ['slug' => 'automotive', 'name' => '自動車関連', 'sort_order' => '17', 'notes' => '自動車販売、整備、車検、鈑金、カー用品、中古車販売など。中古車在庫はポータル・在庫管理に依存しやすい一方、整備・車検側には更新余地がある。'],
            ['slug' => 'agriculture', 'name' => '農業・一次産業', 'sort_order' => '18', 'notes' => '農業法人、果樹園、農園、直売所、一次産業関連。生育状況、収穫情報、直売所案内、農園体験、採用、ブランド発信などを観測する。'],
            ['slug' => 'other', 'name' => 'その他', 'sort_order' => '19', 'notes' => '上記に分類できないもの。宗教法人、NPO、協同組合、商工会議所、団体、公共性の強い組織など。分類不能または足切り用の受け皿。'],
        ];

        foreach ($rows as $row) {
            DB::table('industries')->updateOrInsert(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'parent_id' => null,
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
