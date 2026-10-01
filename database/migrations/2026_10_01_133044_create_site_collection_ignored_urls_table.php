<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_collection_ignored_urls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('collection_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->timestamps();

            $table->unique(['collection_id', 'url']);
            $table->index(['team_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_collection_ignored_urls');
    }
};
