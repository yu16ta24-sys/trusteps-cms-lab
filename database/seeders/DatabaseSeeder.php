<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PrefectureSeeder::class,
            MunicipalitySeeder::class,
            IndustrySeeder::class,
            IndustryScoreAxisSeeder::class,
            UpdateTargetSeeder::class,
            ReasonCodeSeeder::class,
            RegionSeeder::class,
            PrefectureRegionSeeder::class,
        ]);
    }
}
