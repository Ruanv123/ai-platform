<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->primary(['tag_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('tags');
    }
};
