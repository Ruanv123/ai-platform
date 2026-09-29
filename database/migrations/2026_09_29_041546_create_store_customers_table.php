<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('external_id', 255);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_e164', 32)->nullable();
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
            $table->unique('contact_id');
            $table->index(['team_id', 'phone_e164']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_customers');
    }
};
