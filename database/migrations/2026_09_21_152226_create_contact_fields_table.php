<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_fields', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 16);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('contact_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('contact_field_id')->constrained('contact_fields')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['contact_field_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_field_values');
        Schema::dropIfExists('contact_fields');
    }
};
