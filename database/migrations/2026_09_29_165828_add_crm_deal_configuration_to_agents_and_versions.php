<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->boolean('crm_ensure_deal_enabled')->nullable()->default(null);
            $table->string('crm_pipeline_id', 128)->nullable();
            $table->string('crm_initial_stage_id', 128)->nullable();
        });

        Schema::table('agent_versions', function (Blueprint $table): void {
            $table->json('crm_tools')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_versions', function (Blueprint $table): void {
            $table->dropColumn('crm_tools');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn(['crm_ensure_deal_enabled', 'crm_pipeline_id', 'crm_initial_stage_id']);
        });
    }
};
