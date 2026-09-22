<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flow_execution_steps', function (Blueprint $table): void {
            $table->json('http_request')->nullable()->after('variables_snapshot');
            $table->json('http_response')->nullable()->after('http_request');
            $table->unsignedSmallInteger('http_status')->nullable()->after('http_response');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('http_status');
            $table->text('ai_prompt')->nullable()->after('retry_count');
            $table->text('ai_response')->nullable()->after('ai_prompt');
            $table->unsignedInteger('ai_input_tokens')->nullable()->after('ai_response');
            $table->unsignedInteger('ai_output_tokens')->nullable()->after('ai_input_tokens');
            $table->decimal('ai_cost_brl', 12, 4)->nullable()->after('ai_output_tokens');
            $table->json('ai_tool_calls')->nullable()->after('ai_cost_brl');
        });
    }

    public function down(): void
    {
        Schema::table('flow_execution_steps', function (Blueprint $table): void {
            $table->dropColumn([
                'http_request',
                'http_response',
                'http_status',
                'retry_count',
                'ai_prompt',
                'ai_response',
                'ai_input_tokens',
                'ai_output_tokens',
                'ai_cost_brl',
                'ai_tool_calls',
            ]);
        });
    }
};
