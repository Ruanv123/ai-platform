<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_template_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->string('event', 32);
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->string('old_category', 32)->nullable();
            $table->string('new_category', 32)->nullable();
            $table->text('reason')->nullable();
            $table->text('explanation')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at');

            $table->index(['whatsapp_template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_histories');
    }
};
