<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_variables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_variables');
    }
};
