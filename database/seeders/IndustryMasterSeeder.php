<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustryMasterSeeder extends Seeder
{
    public function run(): void
    {
        // 外部キー制約を一時無効化
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('industry_scores')->truncate();
        DB::table('industries')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        $data = $this->getData();
        $sortOrder = 0;

        foreach ($data as $parent) {
            $sortOrder += 10;
            $parentId = DB::table('industries')->insertGetId([
                'slug'            => $parent['slug'],
                'name'            => $parent['name'],
                'parent_id'       => null,
                'sort_order'      => $sortOrder,
                'is_active'       => 1,
                'notes'           => $parent['notes'] ?? null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $childSort = 0;
            foreach ($parent['children'] as $child) {
                $childSort += 10;
                DB::table('industries')->insert([
                    'slug'            => $child['slug'],
                    'name'            => $child['name'],
                    'parent_id'       => $parentId,
                    'sort_order'      => $childSort,
                    'is_active'       => 1,
                    'notes'           => $child['notes'] ?? null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        $count = DB::table('industries')->count();
        $this->command->info("業種マスタを投入しました: {$count}件");
    }

    private function getData(): array
    {
        return [
            // ===== A: 建設・工事 =====
            [
                'slug'  => 'construction',
                'name'  => '建設・工事',
                'notes' => '建設業全般。工務店・塗装・外構など。HP改善余地は中分類によって大きく異なる。',
                'children' => [
                    ['slug' => 'construction_builder',      'name' => '工務店・注文住宅',         'notes' => 'WP普及高、HP成熟気味。余地は更新運用改善が中心。'],
                    ['slug' => 'construction_reform',       'name' => 'リフォーム・増改築',        'notes' => '施工事例・before-after更新ネタ多。余地あり。'],
                    ['slug' => 'construction_paint',        'name' => '塗装・外壁・屋根',          'notes' => 'HP放置気味が多い。余地大。施工事例が武器になる。'],
                    ['slug' => 'construction_exterior',     'name' => '外構・エクステリア',        'notes' => '施工事例更新余地あり。写真ベースのHP更新向き。'],
                    ['slug' => 'construction_equipment',    'name' => '電気・空調・設備工事',      'notes' => 'HP薄い傾向。BtoC向けなら余地あり。'],
                    ['slug' => 'construction_plumbing',     'name' => '水道・給排水工事',          'notes' => 'HP持つところ少ない。緊急対応訴求余地あり。'],
                    ['slug' => 'construction_civil',        'name' => '解体・土木・測量',          'notes' => 'BtoB色強い。HP余地小。'],
                    ['slug' => 'construction_scaffold',     'name' => '足場・仮設工事',            'notes' => 'BtoB。HP価値低い。'],
                ],
            ],

            // ===== B: 不動産 =====
            [
                'slug'  => 'real_estate',
                'name'  => '不動産',
                'notes' => '不動産業全般。ポータル依存が高く、独自HP改善余地は限定的。',
                'children' => [
                    ['slug' => 'realestate_sale',           'name' => '不動産売買・仲介',          'notes' => 'SUUMO等ポータル依存高。独自HP余地は差別化コンテンツ。'],
                    ['slug' => 'realestate_rental',         'name' => '不動産賃貸・管理',          'notes' => 'ポータル依存高。管理会社向けHP余地あり。'],
                    ['slug' => 'realestate_land',           'name' => '土地活用・開発',            'notes' => 'BtoB色あり。信頼構築HP余地あり。'],
                    ['slug' => 'realestate_parking',        'name' => '駐車場・倉庫賃貸',          'notes' => 'HP価値低い。'],
                ],
            ],

            // ===== C: 士業・専門サービス =====
            [
                'slug'  => 'professional',
                'name'  => '士業・専門サービス',
                'notes' => '士業・コンサル系。信頼構築・問い合わせ導線がHP価値の中心。',
                'children' => [
                    ['slug' => 'prof_tax',                  'name' => '税理士・会計士',            'notes' => 'HP信頼重要。コラム・事例更新余地あり。'],
                    ['slug' => 'prof_judicial',             'name' => '行政書士・司法書士',        'notes' => 'HP余地あり。サービス内容の明確化が鍵。'],
                    ['slug' => 'prof_labor',                'name' => '社会保険労務士',            'notes' => 'HP余地あり。採用・労務コラム更新向き。'],
                    ['slug' => 'prof_lawyer',               'name' => '弁護士・法律事務所',        'notes' => '成熟気味だが余地あり。相談事例・コラム更新。'],
                    ['slug' => 'prof_surveyor',             'name' => '土地家屋調査士',            'notes' => 'HP薄い傾向。余地あり。'],
                    ['slug' => 'prof_consultant',           'name' => '中小企業診断士・経営コンサル', 'notes' => 'HP余地あり。実績・事例更新。'],
                ],
            ],

            // ===== D: 医療・歯科 =====
            [
                'slug'  => 'medical',
                'name'  => '医療・歯科',
                'notes' => '医療機関。HP重要度高く予約導線・信頼構築が中心。',
                'children' => [
                    ['slug' => 'medical_dental',            'name' => '歯科医院',                  'notes' => 'HP重要度高。治療メニュー・スタッフ紹介更新余地大。'],
                    ['slug' => 'medical_clinic',            'name' => '内科・クリニック',          'notes' => 'HP重要。予約導線・診療案内更新余地あり。'],
                    ['slug' => 'medical_ortho',             'name' => '整形外科・リハビリ',        'notes' => 'HP重要度中。リハビリ事例更新余地あり。'],
                    ['slug' => 'medical_skin',              'name' => '皮膚科・美容皮膚科',        'notes' => 'SNS依存高。HP+SNS余地あり。'],
                    ['slug' => 'medical_eye_ear',           'name' => '眼科・耳鼻科',              'notes' => 'HP必要だが更新少ない。'],
                    ['slug' => 'medical_animal',            'name' => '動物病院',                  'notes' => 'HP余地あり。スタッフ・設備紹介更新向き。'],
                    ['slug' => 'medical_ob',                'name' => '産婦人科・助産院',          'notes' => 'HP重要。安心感訴求コンテンツ余地あり。'],
                ],
            ],

            // ===== E: 整体・治療院 =====
            [
                'slug'  => 'therapy',
                'name'  => '整体・治療院',
                'notes' => '施術系。競合多くHP差別化余地大。放置HP多い。',
                'children' => [
                    ['slug' => 'therapy_seitai',            'name' => '整体・カイロプラクティック', 'notes' => 'HP余地大。放置多い。症状・改善事例更新向き。'],
                    ['slug' => 'therapy_seikotsu',          'name' => '整骨院・接骨院',            'notes' => 'HP余地大。競合多い。差別化コンテンツ重要。'],
                    ['slug' => 'therapy_acupuncture',       'name' => '鍼灸院',                    'notes' => 'HP余地あり。施術説明・症状別コンテンツ向き。'],
                    ['slug' => 'therapy_massage',           'name' => 'マッサージ・リラクゼーション', 'notes' => 'HP余地あり。'],
                ],
            ],

            // ===== F: 美容・サロン =====
            [
                'slug'  => 'beauty',
                'name'  => '美容・サロン',
                'notes' => '美容系。SNS依存高いが独自HP余地も残る。',
                'children' => [
                    ['slug' => 'beauty_hair',               'name' => '美容院・ヘアサロン',        'notes' => '競合激しい。HP+SNS連携余地。スタイル実績更新向き。'],
                    ['slug' => 'beauty_esthetic',           'name' => 'エステ・脱毛サロン',        'notes' => 'HP余地あり。メニュー・効果訴求更新向き。'],
                    ['slug' => 'beauty_nail',               'name' => 'ネイル・アイラッシュ',      'notes' => 'SNS依存高。HP余地中。'],
                    ['slug' => 'beauty_barber',             'name' => '理容室・バーバー',          'notes' => 'HP薄い傾向。余地あり。'],
                    ['slug' => 'beauty_relaxation',         'name' => 'リラクゼーション・スパ',    'notes' => 'HP余地あり。雰囲気・メニュー訴求向き。'],
                ],
            ],

            // ===== G: 飲食 =====
            [
                'slug'  => 'food',
                'name'  => '飲食',
                'notes' => '飲食業全般。ポータル依存の高低で余地が大きく変わる。',
                'children' => [
                    ['slug' => 'food_cafe',                 'name' => 'カフェ・喫茶店',            'notes' => 'HP+SNS余地大。雰囲気・メニュー訴求が中心。'],
                    ['slug' => 'food_restaurant',           'name' => 'レストラン・洋食',          'notes' => 'HP余地あり。コース・予約導線更新向き。'],
                    ['slug' => 'food_japanese',             'name' => '和食・割烹・寿司',          'notes' => 'HP余地あり。格式・素材訴求コンテンツ向き。'],
                    ['slug' => 'food_izakaya',              'name' => '居酒屋・ダイニングバー',    'notes' => '食べログ依存高。HP余地中。'],
                    ['slug' => 'food_ramen',                'name' => 'ラーメン・麺類',            'notes' => '食べログ依存高。HP余地小。'],
                    ['slug' => 'food_yakiniku',             'name' => '焼肉・焼き鳥・鉄板',       'notes' => '食べログ依存高。'],
                    ['slug' => 'food_takeout',              'name' => 'テイクアウト・弁当・デリバリー', 'notes' => 'HP+注文導線余地あり。'],
                    ['slug' => 'food_bakery',               'name' => 'パン・スイーツ・菓子店',   'notes' => 'HP+SNS余地大。商品紹介・季節更新向き。'],
                    ['slug' => 'food_catering',             'name' => '給食・仕出し（BtoB）',      'notes' => 'HP価値低い。'],
                ],
            ],

            // ===== H: 小売 =====
            [
                'slug'  => 'retail',
                'name'  => '小売',
                'notes' => '小売業全般。地域密着系は独自HP余地あり。大手ポータル競合に注意。',
                'children' => [
                    ['slug' => 'retail_food',               'name' => '食品・青果・鮮魚',          'notes' => 'HP余地あり。地域密着・こだわり訴求向き。'],
                    ['slug' => 'retail_liquor',             'name' => '酒屋・ワインショップ',      'notes' => 'HP余地あり。商品紹介・イベント更新向き。'],
                    ['slug' => 'retail_flower',             'name' => '花屋・植物販売',            'notes' => 'HP+SNS余地大。季節商品・アレンジ更新向き。'],
                    ['slug' => 'retail_goods',              'name' => '雑貨・インテリア',          'notes' => 'HP余地あり。商品紹介更新向き。'],
                    ['slug' => 'retail_apparel',            'name' => 'アパレル・古着',            'notes' => 'SNS依存高。HP余地中。'],
                    ['slug' => 'retail_book',               'name' => '書店・文具・玩具',          'notes' => 'HP余地中。'],
                    ['slug' => 'retail_sports',             'name' => '自転車・スポーツ用品',      'notes' => 'HP余地あり。'],
                    ['slug' => 'retail_pet',                'name' => 'ペットショップ',            'notes' => 'HP余地あり。在庫・ブリーダー情報更新向き。'],
                    ['slug' => 'retail_glasses',            'name' => '眼鏡・補聴器',             'notes' => 'HP余地あり。'],
                ],
            ],

            // ===== I: 宿泊・観光・レジャー =====
            [
                'slug'  => 'lodging_leisure',
                'name'  => '宿泊・観光・レジャー',
                'notes' => '宿泊・レジャー系。OTA依存の高低で余地が変わる。',
                'children' => [
                    ['slug' => 'lodging_hotel',             'name' => 'ホテル・旅館',              'notes' => 'じゃらん・楽天等OTA依存高。独自HP余地は直予約導線。'],
                    ['slug' => 'lodging_guesthouse',        'name' => '民宿・ゲストハウス',        'notes' => 'HP余地あり。個性・雰囲気訴求向き。'],
                    ['slug' => 'lodging_glamping',          'name' => 'キャンプ場・グランピング',  'notes' => 'HP余地大。写真・体験訴求が有効。'],
                    ['slug' => 'lodging_experience',        'name' => '観光農園・体験施設',        'notes' => 'HP余地大。季節・体験メニュー更新向き。'],
                    ['slug' => 'lodging_golf',              'name' => 'ゴルフ場・スポーツ施設',   'notes' => 'HP余地あり。コース・予約導線更新向き。'],
                    ['slug' => 'lodging_fitness',           'name' => 'フィットネス・スポーツジム', 'notes' => 'HP+会員導線余地あり。'],
                ],
            ],

            // ===== J: 保育・教育 =====
            [
                'slug'  => 'education',
                'name'  => '保育・教育',
                'notes' => '教育・保育系。保護者向けHP重要度高く更新ネタも多い。',
                'children' => [
                    ['slug' => 'edu_nursery',               'name' => '保育園・幼稚園・こども園',  'notes' => 'HP重要。園の様子・行事更新余地大。'],
                    ['slug' => 'edu_cram',                  'name' => '学習塾・予備校',            'notes' => 'HP重要。合格実績・授業紹介更新余地あり。'],
                    ['slug' => 'edu_lesson',                'name' => '習い事・スクール（音楽・体操等）', 'notes' => 'HP余地大。体験・発表会更新向き。'],
                    ['slug' => 'edu_language',              'name' => '語学教室・英会話',          'notes' => 'HP余地あり。'],
                    ['slug' => 'edu_vocational',            'name' => '資格・職業訓練スクール',    'notes' => 'HP余地あり。合格実績・カリキュラム更新向き。'],
                ],
            ],

            // ===== K: 介護・福祉 =====
            [
                'slug'  => 'welfare_care',
                'name'  => '介護・福祉',
                'notes' => '介護・福祉系。入居検討者・家族向けHP重要度高い。',
                'children' => [
                    ['slug' => 'care_elderly',              'name' => '老人ホーム・デイサービス',  'notes' => 'HP重要。施設の様子・スタッフ紹介更新余地大。'],
                    ['slug' => 'care_visiting',             'name' => '訪問介護・訪問看護',        'notes' => 'HP余地あり。サービス内容・スタッフ紹介更新向き。'],
                    ['slug' => 'care_disability',           'name' => '障害者支援・就労支援',      'notes' => 'HP余地あり。'],
                    ['slug' => 'care_therapy_child',        'name' => '児童発達支援・療育',        'notes' => 'HP余地あり。保護者向け情報更新向き。'],
                ],
            ],

            // ===== L: 自動車・輸送 =====
            [
                'slug'  => 'automotive',
                'name'  => '自動車・輸送',
                'notes' => '自動車・輸送系。ポータル依存の高低で余地が変わる。',
                'children' => [
                    ['slug' => 'auto_dealer',               'name' => '自動車販売・ディーラー',    'notes' => 'カーセンサー等依存あり。新車・試乗情報更新余地あり。'],
                    ['slug' => 'auto_used',                 'name' => '中古車販売',                'notes' => 'ポータル依存高。独自HP余地は信頼構築。'],
                    ['slug' => 'auto_repair',               'name' => '車検・整備・板金',          'notes' => 'HP余地あり。放置多い。料金・事例更新向き。'],
                    ['slug' => 'auto_bike',                 'name' => 'バイク販売・整備',          'notes' => 'HP余地あり。'],
                    ['slug' => 'auto_rental',               'name' => 'レンタカー・カーシェア',    'notes' => 'HP余地あり。車種・料金プラン更新向き。'],
                    ['slug' => 'auto_transport',            'name' => '運送・宅配（BtoB）',        'notes' => 'BtoB色強い。HP余地低め。'],
                    ['slug' => 'auto_taxi',                 'name' => 'タクシー・ハイヤー',        'notes' => 'HP余地低い。'],
                ],
            ],

            // ===== M: 製造業 =====
            [
                'slug'  => 'manufacturing',
                'name'  => '製造業',
                'notes' => '製造業全般。BtoB色が強いが直販・産直系は余地あり。',
                'children' => [
                    ['slug' => 'mfg_food',                  'name' => '食品製造・加工',            'notes' => 'HP余地あり。直販・EC展開なら余地大。'],
                    ['slug' => 'mfg_metal',                 'name' => '金属・機械加工',            'notes' => 'BtoB。HP余地中。実績・設備紹介向き。'],
                    ['slug' => 'mfg_print',                 'name' => '印刷・包装',                'notes' => 'BtoB色強い。'],
                    ['slug' => 'mfg_wood',                  'name' => '木工・家具製造',            'notes' => 'HP余地あり。作品紹介・オーダー訴求向き。'],
                    ['slug' => 'mfg_other',                 'name' => 'その他製造',                'notes' => '個別判断。'],
                ],
            ],

            // ===== N: BtoB・法人サービス =====
            [
                'slug'  => 'btob_service',
                'name'  => 'BtoB・法人サービス',
                'notes' => 'BtoB向けサービス業。HP余地は信頼構築・実績訴求が中心。',
                'children' => [
                    ['slug' => 'btob_cleaning',             'name' => '清掃・ビルメンテナンス',   'notes' => 'BtoB。HP余地低め。'],
                    ['slug' => 'btob_security',             'name' => '警備・セキュリティ',        'notes' => 'BtoB。HP余地低め。'],
                    ['slug' => 'btob_staffing',             'name' => '人材派遣・紹介',            'notes' => 'HP余地あり。求職者・企業向け両面更新向き。'],
                    ['slug' => 'btob_ad',                   'name' => '広告・デザイン・印刷',      'notes' => 'HP余地あり。実績ポートフォリオ更新向き。'],
                    ['slug' => 'btob_it',                   'name' => 'IT・システム開発',          'notes' => 'HP余地あり。実績・技術紹介更新向き。'],
                ],
            ],

            // ===== O: 農業・一次産業 =====
            [
                'slug'  => 'agriculture',
                'name'  => '農業・一次産業',
                'notes' => '農業・漁業・林業。直販・産直系はHP+EC余地大。',
                'children' => [
                    ['slug' => 'agri_farm',                 'name' => '農業・直売所',              'notes' => 'HP+EC余地大。季節・商品更新向き。'],
                    ['slug' => 'agri_fish',                 'name' => '漁業・水産加工',            'notes' => 'HP余地あり。産地・商品訴求向き。'],
                    ['slug' => 'agri_forest',               'name' => '林業・木材',                'notes' => 'HP余地低め。'],
                ],
            ],

            // ===== P: その他・対象外 =====
            [
                'slug'  => 'other',
                'name'  => 'その他・対象外',
                'notes' => '営業対象外または分類不能。',
                'children' => [
                    ['slug' => 'other_finance',             'name' => '金融・保険',                'notes' => '規制業種。HP余地低め。'],
                    ['slug' => 'other_public',              'name' => '公共・行政・団体',          'notes' => '営業対象外。'],
                    ['slug' => 'other_religion',            'name' => '宗教法人',                  'notes' => '営業対象外。'],
                    ['slug' => 'other_unknown',             'name' => '分類不能',                  'notes' => '特定できない場合。'],
                ],
            ],
        ];
    }
}
