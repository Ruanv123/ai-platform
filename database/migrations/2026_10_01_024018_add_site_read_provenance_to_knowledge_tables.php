<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->boolean('is_site_collection')->default(false);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('site_read_page_id')
                ->nullable()
                ->after('collection_id')
                ->constrained('site_read_pages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_read_page_id');
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn('is_site_collection');
        });
    }
};
