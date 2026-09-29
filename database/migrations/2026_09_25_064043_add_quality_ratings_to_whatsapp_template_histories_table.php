<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_template_histories', function (Blueprint $table): void {
            $table->string('old_quality_rating', 32)->nullable();
            $table->string('new_quality_rating', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_template_histories', function (Blueprint $table): void {
            $table->dropColumn(['old_quality_rating', 'new_quality_rating']);
        });
    }
};
