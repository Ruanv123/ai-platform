<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $table->string('meta_template_id')->nullable();
            $table->string('name');
            $table->string('language', 16);
            $table->string('status', 32)->default('draft');
            $table->string('requested_category', 32);
            $table->string('assigned_category', 32)->nullable();
            $table->text('body');
            $table->string('header_type', 32)->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables');
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_explanation')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'meta_template_id']);
            $table->unique(['whatsapp_account_id', 'name', 'language']);
            $table->index(['whatsapp_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
