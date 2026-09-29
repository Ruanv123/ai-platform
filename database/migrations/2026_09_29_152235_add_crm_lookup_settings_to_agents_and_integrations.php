<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('crm_lookup_enabled')->nullable()->default(null);
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->json('crm_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('crm_settings');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('crm_lookup_enabled');
        });

    }
};
