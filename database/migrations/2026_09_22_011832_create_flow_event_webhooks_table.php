<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_event_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_event_id')->constrained('flow_events')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->text('secret');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['flow_event_id', 'url']);
            $table->index(['team_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_event_webhooks');
    }
};
