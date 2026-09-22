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
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('sent_by_user_id')->nullable()->after('provider_message_id')->constrained('users')->nullOnDelete();
            $table->index(['team_id', 'sent_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_team_id_sent_by_user_id_index');
            $table->dropForeign(['sent_by_user_id']);
            $table->dropColumn('sent_by_user_id');
        });
    }
};
