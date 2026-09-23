<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('schedulable_calendar_id')->nullable()->constrained('schedulable_calendars')->nullOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('google_event_id', 512);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('booked');
            $table->timestamps();

            $table->index(['team_id', 'contact_id', 'status', 'starts_at']);
            $table->index(['schedulable_calendar_id', 'status', 'starts_at']);
            $table->unique(['schedulable_calendar_id', 'google_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
