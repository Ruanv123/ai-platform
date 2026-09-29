<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('store_customer_id')->nullable()->constrained('store_customers')->nullOnDelete();
            $table->string('customer_external_id')->nullable();
            $table->string('external_id', 255);
            $table->string('order_number', 64);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency_code', 3);
            $table->string('financial_status', 32)->nullable();
            $table->string('fulfillment_status', 32)->nullable();
            $table->timestamp('ordered_at');
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
            $table->index(['team_id', 'store_customer_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};
