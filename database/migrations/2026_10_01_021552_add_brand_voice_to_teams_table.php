<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->text('brand_voice')->nullable()->after('address');
            $table->jsonb('brand_voice_examples')->nullable()->after('brand_voice');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['brand_voice', 'brand_voice_examples']);
        });
    }
};
