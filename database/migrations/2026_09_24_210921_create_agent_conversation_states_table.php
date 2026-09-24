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
        Schema::create('agent_conversation_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->boolean('is_paused')->default(false);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('pause_duration_minutes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'contact_id', 'channel_id'], 'agent_conversation_states_pair_unique');
            $table->index(['team_id', 'is_paused', 'assigned_user_id'], 'agent_conversation_states_assignment_index');
        });

        Schema::create('agent_conversation_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('previous_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 24);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'contact_id', 'channel_id', 'created_at'], 'agent_conversation_audits_pair_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_conversation_audits');
        Schema::dropIfExists('agent_conversation_states');
    }
};
