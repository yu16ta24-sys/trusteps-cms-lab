<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_scores')
            ->whereIn('axis', ['dev_difficulty', 'portal_dependence'])
            ->update(['value' => DB::raw('5 - value')]);
    }

    public function down(): void
    {
        DB::table('company_scores')
            ->whereIn('axis', ['dev_difficulty', 'portal_dependence'])
            ->update(['value' => DB::raw('5 - value')]);
    }
};
