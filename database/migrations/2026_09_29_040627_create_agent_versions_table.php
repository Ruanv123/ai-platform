<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('objective', 32);
            $table->json('ecommerce_tools');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'number']);
            $table->index(['agent_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_versions');
    }
};
