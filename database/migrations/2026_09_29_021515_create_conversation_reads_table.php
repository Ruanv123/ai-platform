<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'contact_id', 'channel_id'], 'conversation_reads_pair_unique');
        });

        DB::statement(
            'insert into conversation_reads (team_id, contact_id, channel_id, last_read_message_id, created_at, updated_at) '
            .'select team_id, contact_id, channel_id, max(id), now(), now() '
            .'from messages group by team_id, contact_id, channel_id'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
    }
};
