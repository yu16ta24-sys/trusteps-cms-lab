<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustryScorePresetSeeder extends Seeder
{
    private const AXES = [
        'customer_research_depth_score' => [
            'construction_builder' => 5,
            'construction_reform'  => 5,
            'construction_paint'   => 4,
            'construction_exterior' => 5,
            'construction_equipment' => 3,
            'construction_civil'   => 3,
            'realestate_sale'      => 5,
            'realestate_rental'    => 4,
            'prof_tax'             => 4,
            'prof_judicial'        => 4,
            'prof_labor'           => 4,
            'prof_lawyer'          => 4,
            'prof_consultant'      => 4,
            'medical_dental'       => 4,
            'medical_clinic'       => 3,
            'therapy_seitai'       => 3,
            'therapy_seikotsu'     => 3,
            'beauty_hair'          => 3,
            'beauty_esthetic'      => 4,
            'edu_nursery'          => 4,
            'edu_cram'             => 4,
            'edu_lesson'           => 3,
            'care_elderly'         => 5,
            'care_visiting'        => 4,
            'food_restaurant'      => 2,
            'food_cafe'            => 2,
            'food_izakaya'         => 2,
            'auto_dealer'          => 4,
            'auto_used'            => 4,
            'auto_repair'          => 3,
            'btob_it'              => 4,
            'btob_staffing'        => 4,
            'agri_farm'            => 3,
            'lodging_hotel'        => 4,
            'lodging_fitness'      => 3,
        ],
        'conversion_value_score' => [
            'construction_builder' => 5,
            'construction_reform'  => 5,
            'construction_paint'   => 5,
            'construction_exterior' => 5,
            'construction_equipment' => 4,
            'construction_civil'   => 4,
            'realestate_sale'      => 5,
            'realestate_rental'    => 4,
            'prof_tax'             => 4,
            'prof_judicial'        => 4,
            'prof_labor'           => 4,
            'prof_lawyer'          => 5,
            'prof_consultant'      => 4,
            'medical_dental'       => 4,
            'medical_clinic'       => 3,
            'therapy_seitai'       => 3,
            'therapy_seikotsu'     => 3,
            'beauty_hair'          => 3,
            'beauty_esthetic'      => 4,
            'edu_nursery'          => 4,
            'edu_cram'             => 4,
            'edu_lesson'           => 3,
            'care_elderly'         => 5,
            'care_visiting'        => 4,
            'food_restaurant'      => 2,
            'food_cafe'            => 2,
            'food_izakaya'         => 2,
            'auto_dealer'          => 5,
            'auto_used'            => 4,
            'auto_repair'          => 3,
            'btob_it'              => 5,
            'btob_staffing'        => 4,
            'agri_farm'            => 3,
            'lodging_hotel'        => 4,
            'lodging_fitness'      => 3,
        ],
    ];

    public function run(): void
    {
        $now = now()->toDateTimeString();

        foreach (self::AXES as $axisKey => $industryMap) {
            foreach ($industryMap as $industryKey => $value) {
                DB::table('industry_scores')->updateOrInsert(
                    [
                        'industry_key' => $industryKey,
                        'axis_key'     => $axisKey,
                    ],
                    [
                        'value'      => $value,
                        'confidence' => null,
                        'score_type' => 'hypothesis',
                        'note'       => 'preset v1.0',
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        $total = count(self::AXES) * count(reset(self::AXES));
        $this->command->info("IndustryScorePresetSeeder: {$total} 件を投入しました。");
    }
}
