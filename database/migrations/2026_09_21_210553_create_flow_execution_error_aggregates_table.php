<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_execution_error_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->foreignUuid('flow_version_id')->constrained('flow_versions')->restrictOnDelete();
            $table->string('node_id');
            $table->string('error_code');
            $table->date('period_date');
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamps();

            $table->unique(
                ['team_id', 'flow_id', 'flow_version_id', 'node_id', 'error_code', 'period_date'],
                'flow_execution_error_aggregate_unique',
            );
            $table->index(['team_id', 'flow_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_execution_error_aggregates');
    }
};
