<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_event_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_event_id')->constrained('flow_events')->cascadeOnDelete();
            $table->foreignUuid('flow_event_webhook_id')->constrained('flow_event_webhooks')->cascadeOnDelete();
            $table->foreignUuid('flow_event_emission_id')->constrained('flow_event_emissions')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('error')->nullable();
            $table->json('payload');
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_event_webhook_id', 'flow_event_emission_id']);
            $table->index(['team_id', 'status', 'created_at']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_event_deliveries');
    }
};
