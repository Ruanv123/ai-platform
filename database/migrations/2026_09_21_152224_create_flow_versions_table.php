<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->json('dsl');
            $table->string('category', 16);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'number']);
            $table->index(['flow_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_versions');
    }
};
