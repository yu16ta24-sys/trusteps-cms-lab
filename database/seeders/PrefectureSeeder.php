<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrefectureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            ['code' => '01', 'name' => '北海道', 'prefecture_scale' => 'large_metro_prefecture'],
            ['code' => '02', 'name' => '青森県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '03', 'name' => '岩手県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '04', 'name' => '宮城県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '05', 'name' => '秋田県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '06', 'name' => '山形県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '07', 'name' => '福島県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '08', 'name' => '茨城県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '09', 'name' => '栃木県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '10', 'name' => '群馬県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '11', 'name' => '埼玉県', 'prefecture_scale' => 'large_metro_prefecture'],
            ['code' => '12', 'name' => '千葉県', 'prefecture_scale' => 'large_metro_prefecture'],
            ['code' => '13', 'name' => '東京都', 'prefecture_scale' => 'mega_metro_core'],
            ['code' => '14', 'name' => '神奈川県', 'prefecture_scale' => 'mega_metro_core'],
            ['code' => '15', 'name' => '新潟県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '16', 'name' => '富山県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '17', 'name' => '石川県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '18', 'name' => '福井県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '19', 'name' => '山梨県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '20', 'name' => '長野県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '21', 'name' => '岐阜県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '22', 'name' => '静岡県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '23', 'name' => '愛知県', 'prefecture_scale' => 'mega_metro_core'],
            ['code' => '24', 'name' => '三重県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '25', 'name' => '滋賀県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '26', 'name' => '京都府', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '27', 'name' => '大阪府', 'prefecture_scale' => 'mega_metro_core'],
            ['code' => '28', 'name' => '兵庫県', 'prefecture_scale' => 'large_metro_prefecture'],
            ['code' => '29', 'name' => '奈良県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '30', 'name' => '和歌山県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '31', 'name' => '鳥取県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '32', 'name' => '島根県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '33', 'name' => '岡山県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '34', 'name' => '広島県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '35', 'name' => '山口県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '36', 'name' => '徳島県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '37', 'name' => '香川県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '38', 'name' => '愛媛県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '39', 'name' => '高知県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '40', 'name' => '福岡県', 'prefecture_scale' => 'large_metro_prefecture'],
            ['code' => '41', 'name' => '佐賀県', 'prefecture_scale' => 'small_local_prefecture'],
            ['code' => '42', 'name' => '長崎県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '43', 'name' => '熊本県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '44', 'name' => '大分県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '45', 'name' => '宮崎県', 'prefecture_scale' => 'standard_local_prefecture'],
            ['code' => '46', 'name' => '鹿児島県', 'prefecture_scale' => 'regional_core_prefecture'],
            ['code' => '47', 'name' => '沖縄県', 'prefecture_scale' => 'regional_core_prefecture'],
        ];

        foreach ($rows as $row) {
            DB::table('prefectures')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'name_kana' => null,
                    'prefecture_scale' => $row['prefecture_scale'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
