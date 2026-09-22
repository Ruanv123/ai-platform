<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('payload_schema')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_events');
    }
};
