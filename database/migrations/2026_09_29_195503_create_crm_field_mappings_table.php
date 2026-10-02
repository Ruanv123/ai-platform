<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_field_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_field_id')->constrained()->cascadeOnDelete();
            $table->string('crm_field_id');
            $table->string('direction', 16);
            $table->json('option_mappings')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'contact_field_id']);
            $table->unique(['integration_id', 'crm_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_field_mappings');
    }
};
