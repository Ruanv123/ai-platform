<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->foreignUuid('purchase_attribution_id')
                ->nullable()
                ->constrained('purchase_attributions')
                ->nullOnDelete();
            $table->index('purchase_attribution_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropIndex(['purchase_attribution_id']);
            $table->dropConstrainedForeignId('purchase_attribution_id');
        });
    }
};
