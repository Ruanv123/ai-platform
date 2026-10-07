<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_reads', function (Blueprint $table): void {
            $table->unsignedInteger('firecrawl_credits_used')->nullable();
            $table->unsignedInteger('ai_input_tokens')->nullable();
            $table->unsignedInteger('ai_output_tokens')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_reads', function (Blueprint $table): void {
            $table->dropColumn([
                'firecrawl_credits_used',
                'ai_input_tokens',
                'ai_output_tokens',
            ]);
        });
    }
};
