<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->nullable()->constrained('channels')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->json('draft_dsl');
            $table->string('trigger_type', 16)->default('message');
            $table->string('trigger_event')->nullable();
            $table->string('trigger_token_hash')->nullable();
            $table->string('collision_policy', 16)->default('ignore');
            $table->boolean('allow_unknown_contact')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['channel_id', 'trigger_type', 'trigger_event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flows');
    }
};
