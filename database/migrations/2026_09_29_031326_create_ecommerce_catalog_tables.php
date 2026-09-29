<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('bulk_operation_id')->nullable()->after('sync_progress');
            $table->unsignedBigInteger('sync_processed_count')->default(0)->after('bulk_operation_id');
            $table->unsignedBigInteger('sync_total_count')->nullable()->after('sync_processed_count');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('title');
            $table->text('description');
            $table->string('handle');
            $table->text('url')->nullable();
            $table->string('status', 24);
            $table->boolean('online_store_published')->default(false);
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
            $table->char('source_hash', 64)->nullable();
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
            $table->index(['team_id', 'shop_id', 'status', 'online_store_published']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('sku')->nullable();
            $table->string('title');
            $table->json('options');
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->boolean('continues_selling_when_out_of_stock')->default(false);
            $table->boolean('available')->default(false);
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
            $table->index(['team_id', 'product_id', 'available']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('external_id', 255);
            $table->string('title');
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('last_import_operation_id')->nullable();
            $table->timestamps();

            $table->primary(['product_id', 'category_id']);
            $table->index(['team_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn(['bulk_operation_id', 'sync_processed_count', 'sync_total_count']);
        });
    }
};
