<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
        });

        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['contact_id', 'channel_id', 'created_at']);
            $table->dropForeign(['channel_id']);
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn('channel_id');
        });

        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->dropColumn('channel_id');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('channel_id');
        });

        // No phone number, test connection, or message row can reference a
        // channel at this point, so the existing rows can be cleared before
        // the primary key is rebuilt as a UUID.
        DB::table('channels')->delete();

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropPrimary('channels_pkey');
            $table->dropColumn('id');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignUuid('channel_id')->after('contact_id')->constrained()->cascadeOnDelete();
            $table->index(['contact_id', 'channel_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
            $table->dropColumn('channel_id');
        });

        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->dropForeign(['channel_id']);
            $table->dropColumn('channel_id');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['contact_id', 'channel_id', 'created_at']);
            $table->dropForeign(['channel_id']);
            $table->dropColumn('channel_id');
        });

        DB::table('channels')->delete();

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropPrimary('channels_pkey');
            $table->dropColumn('id');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->id();
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->foreignId('channel_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->foreignId('channel_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('channel_id')->after('contact_id')->constrained()->cascadeOnDelete();
            $table->index(['contact_id', 'channel_id', 'created_at']);
        });
    }
};
