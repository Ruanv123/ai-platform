<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            WITH ranked_memberships AS (
                SELECT
                    team_members.id,
                    ROW_NUMBER() OVER (
                        PARTITION BY team_members.user_id
                        ORDER BY
                            CASE WHEN team_members.team_id = users.current_team_id THEN 0 ELSE 1 END,
                            team_members.id
                    ) AS membership_rank
                FROM team_members
                INNER JOIN users ON users.id = team_members.user_id
            )
            DELETE FROM team_members
            USING ranked_memberships
            WHERE team_members.id = ranked_memberships.id
              AND ranked_memberships.membership_rank > 1
        SQL);

        DB::table('teams')->where('is_personal', true)->update(['is_personal' => false]);

        Schema::table('teams', function (Blueprint $table): void {
            $table->string('segment')->nullable()->after('name');
            $table->string('website')->nullable()->after('segment');
            $table->text('business_description')->nullable()->after('website');
            $table->text('products_services')->nullable()->after('business_description');
            $table->text('target_audience')->nullable()->after('products_services');
            $table->jsonb('business_hours')->nullable()->after('target_audience');
            $table->string('timezone')->nullable()->after('business_hours');
            $table->string('phone')->nullable()->after('timezone');
            $table->string('public_email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('public_email');
        });

        Schema::table('team_members', function (Blueprint $table): void {
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table): void {
            $table->dropUnique('team_members_user_id_unique');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn([
                'segment',
                'website',
                'business_description',
                'products_services',
                'target_audience',
                'business_hours',
                'timezone',
                'phone',
                'public_email',
                'address',
            ]);
        });
    }
};
