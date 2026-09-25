<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->string('app_id')->nullable()->after('app_secret');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn('app_id');
        });
    }
};
