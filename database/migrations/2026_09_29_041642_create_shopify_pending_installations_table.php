<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_pending_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('domain', 255)->unique();
            $table->string('shop_name');
            $table->string('currency_code', 3);
            $table->string('country_code', 2)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->text('credentials');
            $table->char('handoff_token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('handoff_started_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_pending_installations');
    }
};
