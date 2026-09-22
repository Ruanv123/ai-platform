<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 64);
            $table->string('provider', 64)->nullable();
            $table->text('credentials');
            $table->string('status', 24)->default('never_tested');
            $table->text('status_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
