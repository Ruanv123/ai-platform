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
        Schema::table('agent_conversation_states', function (Blueprint $table) {
            $table->timestamp('return_requested_at')->nullable();
            $table->timestamp('return_started_at')->nullable();
            $table->foreignId('return_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('return_attempted_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('return_action', 16)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversation_states', function (Blueprint $table) {
            $table->dropForeign(['return_requested_by_user_id']);
            $table->dropForeign(['return_attempted_message_id']);
            $table->dropColumn([
                'return_requested_at',
                'return_started_at',
                'return_requested_by_user_id',
                'return_attempted_message_id',
                'return_action',
            ]);
        });
    }
};
