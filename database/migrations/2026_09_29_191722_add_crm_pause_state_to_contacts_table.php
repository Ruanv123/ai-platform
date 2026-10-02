<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->boolean('crm_agent_paused')->default(false);
            $table->boolean('crm_pause_suppressed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['crm_agent_paused', 'crm_pause_suppressed']);
        });
    }
};
