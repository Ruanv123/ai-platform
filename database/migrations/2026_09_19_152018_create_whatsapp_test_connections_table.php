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
        Schema::create('whatsapp_test_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained('channels')->cascadeOnDelete();
            $table->string('service_url', 2048);
            $table->string('instance_name');
            $table->text('access_token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_test_connections');
    }
};
