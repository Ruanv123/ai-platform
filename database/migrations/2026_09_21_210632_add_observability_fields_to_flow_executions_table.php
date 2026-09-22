<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flow_executions', function (Blueprint $table): void {
            $table->decimal('ai_cost_brl', 12, 4)->default(0)->after('final_reason');
            $table->timestamp('metrics_finalized_at')->nullable()->after('ai_cost_brl');
            $table->timestamp('trace_pruned_at')->nullable()->after('metrics_finalized_at');
            $table->index(['team_id', 'created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('flow_executions', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'created_at', 'status']);
            $table->dropColumn(['ai_cost_brl', 'metrics_finalized_at', 'trace_pruned_at']);
        });
    }
};
