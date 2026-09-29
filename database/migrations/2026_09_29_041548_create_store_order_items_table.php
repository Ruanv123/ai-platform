<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('store_order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('external_id', 255);
            $table->string('title');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->unique(['store_order_id', 'external_id']);
            $table->index(['team_id', 'store_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
    }
};
