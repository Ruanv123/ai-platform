<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('onboarding_step')->default('completed');
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        DB::table('teams')->whereNull('onboarding_completed_at')->update([
            'onboarding_completed_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'onboarding_completed_at']);
        });
    }
};
