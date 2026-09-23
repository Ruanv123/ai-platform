<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedulable_calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('google_calendar_id', 512);
            $table->string('display_name');
            $table->text('display_description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedInteger('minimum_notice_minutes')->default(120);
            $table->json('business_hours')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'google_calendar_id']);
            $table->index(['team_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedulable_calendars');
    }
};
