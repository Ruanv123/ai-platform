<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_execution_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_execution_id')->constrained('flow_executions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('node_id');
            $table->string('result', 16);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('edge_id')->nullable();
            $table->string('edge_source_node_id')->nullable();
            $table->string('edge_target_node_id')->nullable();
            $table->text('edge_label')->nullable();
            $table->text('error')->nullable();
            $table->json('variables_snapshot');
            $table->timestamp('started_at');
            $table->timestamps();

            $table->unique(['flow_execution_id', 'sequence']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_execution_steps');
    }
};
