<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('domain', 255);
            $table->string('name');
            $table->string('currency_code', 3);
            $table->string('country_code', 2)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('sync_status', 24)->default('importing');
            $table->unsignedTinyInteger('sync_progress')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->text('suggested_action')->nullable();
            $table->timestamp('reconciliation_cursor')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('team_id');
            $table->unique('integration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
