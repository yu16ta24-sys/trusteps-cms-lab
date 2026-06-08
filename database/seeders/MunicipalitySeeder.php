<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $prefectureIds = DB::table('prefectures')->pluck('id', 'code');

        $rows = [
            ['code' => '202011', 'prefecture_code' => '20', 'name' => '長野市', 'municipality_scale' => 'core_city', 'population_band' => '200k_500k', 'city_type' => 'core_city', 'is_prefectural_capital' => true, 'is_designated_city' => false, 'is_core_city' => true, 'is_remote_area' => false],
            ['code' => '202029', 'prefecture_code' => '20', 'name' => '松本市', 'municipality_scale' => 'core_city', 'population_band' => '200k_500k', 'city_type' => 'core_city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => true, 'is_remote_area' => false],
            ['code' => '202037', 'prefecture_code' => '20', 'name' => '上田市', 'municipality_scale' => 'local_city', 'population_band' => '100k_200k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '202053', 'prefecture_code' => '20', 'name' => '飯田市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '202177', 'prefecture_code' => '20', 'name' => '佐久市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '382019', 'prefecture_code' => '38', 'name' => '松山市', 'municipality_scale' => 'core_city', 'population_band' => '500k_1m', 'city_type' => 'core_city', 'is_prefectural_capital' => true, 'is_designated_city' => false, 'is_core_city' => true, 'is_remote_area' => false],
            ['code' => '382027', 'prefecture_code' => '38', 'name' => '今治市', 'municipality_scale' => 'local_city', 'population_band' => '100k_200k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '382051', 'prefecture_code' => '38', 'name' => '新居浜市', 'municipality_scale' => 'local_city', 'population_band' => '100k_200k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '382060', 'prefecture_code' => '38', 'name' => '西条市', 'municipality_scale' => 'local_city', 'population_band' => '100k_200k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '382035', 'prefecture_code' => '38', 'name' => '宇和島市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '182010', 'prefecture_code' => '18', 'name' => '福井市', 'municipality_scale' => 'core_city', 'population_band' => '200k_500k', 'city_type' => 'core_city', 'is_prefectural_capital' => true, 'is_designated_city' => false, 'is_core_city' => true, 'is_remote_area' => false],
            ['code' => '182028', 'prefecture_code' => '18', 'name' => '敦賀市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '182079', 'prefecture_code' => '18', 'name' => '鯖江市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '182095', 'prefecture_code' => '18', 'name' => '越前市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
            ['code' => '182109', 'prefecture_code' => '18', 'name' => '坂井市', 'municipality_scale' => 'small_city', 'population_band' => '50k_100k', 'city_type' => 'city', 'is_prefectural_capital' => false, 'is_designated_city' => false, 'is_core_city' => false, 'is_remote_area' => false],
        ];

        foreach ($rows as $row) {
            DB::table('municipalities')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'prefecture_id' => $prefectureIds[$row['prefecture_code']],
                    'name' => $row['name'],
                    'municipality_scale' => $row['municipality_scale'],
                    'population_band' => $row['population_band'],
                    'city_type' => $row['city_type'],
                    'is_prefectural_capital' => $row['is_prefectural_capital'],
                    'is_designated_city' => $row['is_designated_city'],
                    'is_core_city' => $row['is_core_city'],
                    'is_remote_area' => $row['is_remote_area'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
