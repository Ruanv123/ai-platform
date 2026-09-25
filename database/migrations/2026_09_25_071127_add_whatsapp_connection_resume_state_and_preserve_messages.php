<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->timestamp('templates_synced_at')->nullable();
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->timestamp('details_synced_at')->nullable();
            $table->boolean('registration_pin_required')->default(false);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
            $table->uuid('channel_id')->nullable()->change();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('messages')->whereNull('channel_id')->exists()) {
            throw new RuntimeException('A migração não pode ser revertida enquanto houver mensagens preservadas sem Canal.');
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
            $table->uuid('channel_id')->nullable(false)->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn(['details_synced_at', 'registration_pin_required']);
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn('templates_synced_at');
        });
    }
};
