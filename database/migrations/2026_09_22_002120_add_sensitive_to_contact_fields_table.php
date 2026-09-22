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
        Schema::table('contact_fields', function (Blueprint $table): void {
            $table->boolean('is_sensitive')->default(false)->after('options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_fields', function (Blueprint $table): void {
            $table->dropColumn('is_sensitive');
        });
    }
};
