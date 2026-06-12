<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $regions = [
            ['code' => 'hokkaido',        'name' => '北海道',   'sort_order' => 1],
            ['code' => 'tohoku',          'name' => '東北',     'sort_order' => 2],
            ['code' => 'kanto',           'name' => '関東',     'sort_order' => 3],
            ['code' => 'chubu',           'name' => '中部',     'sort_order' => 4],
            ['code' => 'kinki',           'name' => '近畿',     'sort_order' => 5],
            ['code' => 'chugoku_shikoku', 'name' => '中国四国', 'sort_order' => 6],
            ['code' => 'kyushu',          'name' => '九州',     'sort_order' => 7],
            ['code' => 'okinawa',         'name' => '沖縄',     'sort_order' => 8],
        ];

        foreach ($regions as $region) {
            DB::table('regions')->updateOrInsert(
                ['code' => $region['code']],
                array_merge($region, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
