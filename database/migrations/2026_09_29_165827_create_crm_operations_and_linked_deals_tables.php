<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_linked_deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_link_id')->constrained('crm_contact_links')->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_deal_id', 128);
            $table->string('pipeline_id', 128);
            $table->string('stage_id', 128);
            $table->string('status', 32);
            $table->decimal('value', 14, 2)->nullable();
            $table->string('owner_id', 128)->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'external_deal_id']);
            $table->index(['team_id', 'crm_contact_link_id', 'status']);
        });

        Schema::create('crm_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 32);
            $table->string('type', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedTinyInteger('token_refresh_attempts')->default(0);
            $table->text('payload');
            $table->json('summary');
            $table->text('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['team_id', 'status', 'created_at']);
            $table->index(['contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_operations');
        Schema::dropIfExists('crm_linked_deals');
    }
};
