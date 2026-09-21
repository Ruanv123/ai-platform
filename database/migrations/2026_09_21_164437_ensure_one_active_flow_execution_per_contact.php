<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX flow_executions_one_active_contact_channel ON flow_executions (contact_id, channel_id) WHERE status IN ('running', 'waiting', 'paused')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS flow_executions_one_active_contact_channel');
    }
};
