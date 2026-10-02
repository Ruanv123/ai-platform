<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->json('crm_metadata')->nullable();
            $table->timestamp('crm_metadata_loaded_at')->nullable();
            $table->string('crm_singleton_key')->nullable();
        });

        DB::table('integrations')->where('type', 'crm')->update(['crm_singleton_key' => 'crm']);

        Schema::table('integrations', function (Blueprint $table) {
            $table->unique(['team_id', 'crm_singleton_key']);
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'crm_singleton_key']);
            $table->dropColumn(['crm_metadata', 'crm_metadata_loaded_at', 'crm_singleton_key']);
        });
    }
};
