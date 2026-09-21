<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_version_id')->constrained('flow_versions')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignUuid('channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('current_node_id')->nullable();
            $table->json('variables');
            $table->json('trace');
            $table->json('emitted_events');
            $table->json('queued_triggers');
            $table->string('status', 16)->default('running');
            $table->string('waiting_for')->nullable();
            $table->timestamp('wake_at')->nullable();
            $table->timestamp('expires_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('depth')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->text('final_reason')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['contact_id', 'channel_id', 'status']);
            $table->index('wake_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_executions');
    }
};
