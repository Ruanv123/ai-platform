<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->boolean('crm_move_deal_enabled')->nullable();
            $table->boolean('crm_register_note_enabled')->nullable();
            $table->json('crm_allowed_stages')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn([
                'crm_move_deal_enabled',
                'crm_register_note_enabled',
                'crm_allowed_stages',
            ]);
        });
    }
};
