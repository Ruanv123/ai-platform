<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_event_emissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_event_id')->constrained('flow_events')->restrictOnDelete();
            $table->foreignUuid('flow_execution_id')->nullable()->constrained('flow_executions')->nullOnDelete();
            $table->foreignUuid('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->foreignUuid('flow_version_id')->nullable()->constrained('flow_versions')->nullOnDelete();
            $table->string('event_name', 120);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['team_id', 'event_name', 'occurred_at']);
            $table->index(['flow_event_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_event_emissions');
    }
};
