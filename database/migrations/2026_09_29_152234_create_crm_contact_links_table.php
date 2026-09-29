<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contact_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_person_id', 128);
            $table->timestamps();

            $table->unique('contact_id');
            $table->unique(['integration_id', 'external_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_links');
    }
};
