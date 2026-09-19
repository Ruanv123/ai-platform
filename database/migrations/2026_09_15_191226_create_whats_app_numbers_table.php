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
        Schema::create('whats_app_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('method')->default('manual');
            $table->string('waba_id');
            $table->string('phone_number_id');
            $table->text('access_token');
            $table->text('app_secret');
            $table->string('verify_token')->unique();
            $table->string('status')->default('unverified');
            $table->text('last_error')->nullable();
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->unique('team_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_numbers');
    }
};
