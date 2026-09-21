<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'event_type') || Schema::hasColumn('messages', 'payload_type')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->renameColumn('event_type', 'payload_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('messages', 'payload_type') || Schema::hasColumn('messages', 'event_type')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->renameColumn('payload_type', 'event_type');
        });
    }
};
