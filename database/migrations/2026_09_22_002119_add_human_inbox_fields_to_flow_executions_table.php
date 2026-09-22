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
        Schema::table('flow_executions', function (Blueprint $table): void {
            $table->foreignId('human_assigned_user_id')->nullable()->after('waiting_for')->constrained('users')->nullOnDelete();
            $table->timestamp('human_assigned_at')->nullable()->after('human_assigned_user_id');
            $table->timestamp('human_last_activity_at')->nullable()->after('human_assigned_at');
            $table->string('human_transfer_reason')->nullable()->after('human_last_activity_at');
            $table->index(['team_id', 'status', 'waiting_for', 'human_assigned_user_id'], 'flow_executions_human_inbox_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flow_executions', function (Blueprint $table): void {
            $table->dropIndex('flow_executions_human_inbox_index');
            $table->dropForeign(['human_assigned_user_id']);
            $table->dropColumn([
                'human_assigned_user_id',
                'human_assigned_at',
                'human_last_activity_at',
                'human_transfer_reason',
            ]);
        });
    }
};
