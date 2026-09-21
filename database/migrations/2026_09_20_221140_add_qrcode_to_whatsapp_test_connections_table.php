<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->text('qrcode')->nullable()->after('access_token');
            $table->unique('instance_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_test_connections', function (Blueprint $table): void {
            $table->dropUnique(['instance_name']);
            $table->dropColumn('qrcode');
        });
    }
};
