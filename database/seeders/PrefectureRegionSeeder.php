<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrefectureRegionSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = [
            'hokkaido'        => ['01'],
            'tohoku'          => ['02', '03', '04', '05', '06', '07'],
            'kanto'           => ['08', '09', '10', '11', '12', '13', '14'],
            'chubu'           => ['15', '16', '17', '18', '19', '20', '21', '22', '23'],
            'kinki'           => ['24', '25', '26', '27', '28', '29', '30'],
            'chugoku_shikoku' => ['31', '32', '33', '34', '35', '36', '37', '38', '39'],
            'kyushu'          => ['40', '41', '42', '43', '44', '45', '46'],
            'okinawa'         => ['47'],
        ];

        foreach ($mapping as $regionCode => $prefCodes) {
            $regionId = DB::table('regions')->where('code', $regionCode)->value('id');
            if (!$regionId) {
                continue;
            }
            DB::table('prefectures')
                ->whereIn('code', $prefCodes)
                ->update(['region_id' => $regionId]);
        }
    }
}
