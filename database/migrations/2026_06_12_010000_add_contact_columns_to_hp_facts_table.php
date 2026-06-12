<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->string('hp_contact_email', 500)->nullable()->after('has_phone');
            $table->string('hp_contact_form_url', 500)->nullable()->after('hp_contact_email');
            $table->string('hp_contact_phone', 50)->nullable()->after('hp_contact_form_url');
        });
    }

    public function down(): void
    {
        Schema::table('hp_facts', function (Blueprint $table) {
            $table->dropColumn(['hp_contact_email', 'hp_contact_form_url', 'hp_contact_phone']);
        });
    }
};
