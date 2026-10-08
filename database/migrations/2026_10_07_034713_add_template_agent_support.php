<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_template_variables', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('example');
        });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('library_key', 64)->nullable()->after('format');
        });

        Schema::create('agent_whatsapp_template', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->string('usage', 32);
            $table->timestamps();

            $table->unique(['agent_id', 'whatsapp_template_id']);
            $table->index(['agent_id', 'usage']);
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reminder_lead_hours')->default(24);
            $table->unsignedSmallInteger('follow_up_after_hours')->default(24);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable();
        });

        Schema::table('agent_conversation_states', function (Blueprint $table): void {
            $table->foreignId('follow_up_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversation_states', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('follow_up_message_id');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn(['reminder_lead_hours', 'follow_up_after_hours']);
        });

        Schema::dropIfExists('agent_whatsapp_template');

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropColumn('library_key');
        });

        Schema::table('whatsapp_template_variables', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
