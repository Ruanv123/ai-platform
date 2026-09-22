<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'type']);
            $table->index(['team_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'type']);
            $table->unique(['team_id', 'type']);
        });
    }
};
