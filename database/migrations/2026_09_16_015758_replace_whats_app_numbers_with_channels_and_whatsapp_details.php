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
        Schema::dropIfExists('whats_app_numbers');

        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('not_configured');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'type']);
        });

        Schema::create('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained('channels')->cascadeOnDelete();
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_phone_number_id')
                ->unique()
                ->constrained('whatsapp_phone_numbers')
                ->cascadeOnDelete();
            $table->string('method', 32)->default('manual');
            $table->string('waba_id');
            $table->text('access_token');
            $table->text('app_secret');
            $table->string('verify_token')->unique();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
        Schema::dropIfExists('whatsapp_phone_numbers');
        Schema::dropIfExists('channels');
    }
};
