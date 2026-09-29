<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connection_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('channel_id')->unique()->constrained('channels')->cascadeOnDelete();
            $table->text('authorization_code')->nullable();
            $table->text('access_token')->nullable();
            $table->json('debug_token_data')->nullable();
            $table->json('phone_numbers_by_waba')->nullable();
            $table->json('completed_phone_number_waba_ids')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connection_attempts');
    }
};
