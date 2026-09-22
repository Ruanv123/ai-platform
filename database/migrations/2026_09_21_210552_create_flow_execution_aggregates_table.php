<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_execution_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->foreignUuid('flow_version_id')->constrained('flow_versions')->restrictOnDelete();
            $table->string('node_id')->default('');
            $table->date('period_date');
            $table->unsignedInteger('started_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('abandoned_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('stuck_count')->default(0);
            $table->unsignedInteger('entered_count')->default(0);
            $table->unsignedInteger('exited_count')->default(0);
            $table->decimal('ai_cost_brl', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'flow_id', 'flow_version_id', 'node_id', 'period_date'], 'flow_execution_aggregate_unique');
            $table->index(['team_id', 'flow_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_execution_aggregates');
    }
};
