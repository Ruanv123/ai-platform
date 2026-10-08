<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('format', 32)->default('standard')->after('language');
            $table->index(['whatsapp_account_id', 'format']);
        });

        Schema::table('whatsapp_template_components', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_template_id', 'type']);
            $table->unsignedSmallInteger('card_index')->nullable()->after('whatsapp_template_id');
            $table->json('settings')->nullable()->after('media_handle');
            $table->index(['whatsapp_template_id', 'card_index', 'type']);
        });

        Schema::table('whatsapp_template_buttons', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('example');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_template_buttons', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });

        Schema::table('whatsapp_template_components', function (Blueprint $table): void {
            $table->dropIndex(['whatsapp_template_id', 'card_index', 'type']);
            $table->dropColumn(['card_index', 'settings']);
            $table->unique(['whatsapp_template_id', 'type']);
        });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropIndex(['whatsapp_account_id', 'format']);
            $table->dropColumn('format');
        });
    }
};
