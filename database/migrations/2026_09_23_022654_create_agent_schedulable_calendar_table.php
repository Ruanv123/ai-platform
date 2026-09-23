<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_schedulable_calendar', function (Blueprint $table): void {
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUuid('schedulable_calendar_id')->constrained('schedulable_calendars')->cascadeOnDelete();
            $table->primary(['agent_id', 'schedulable_calendar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_schedulable_calendar');
    }
};
