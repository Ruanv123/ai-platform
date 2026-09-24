<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->text('media_path')->nullable();
            $table->string('media_filename')->nullable();
            $table->string('media_mime_type', 127)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->boolean('media_available')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn([
                'media_path',
                'media_filename',
                'media_mime_type',
                'media_size',
                'media_available',
            ]);
        });
    }
};
