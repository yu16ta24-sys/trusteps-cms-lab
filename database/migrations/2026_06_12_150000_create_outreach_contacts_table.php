<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('phase', ['list', 'attacked', 'negotiating', 'contracted', 'rejected', 'hold'])->default('list');
            $table->enum('contact_method', ['email', 'phone', 'form', 'visit', 'other'])->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->string('next_action', 255)->nullable();
            $table->date('next_action_at')->nullable();
            $table->text('memo')->nullable();
            $table->string('created_by', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_contacts');
    }
};
