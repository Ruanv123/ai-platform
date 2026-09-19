<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('objective', 32);
            $table->string('tone', 32);
            $table->boolean('uses_emojis')->default(false);
            $table->string('response_length', 32);
            $table->text('welcome_message');
            $table->json('instructions')->default('[]');
            $table->json('rules')->default('[]');
            $table->json('transfer_triggers')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('agent_collection', function (Blueprint $table): void {
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'collection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_collection');
        Schema::dropIfExists('agents');
    }
};
