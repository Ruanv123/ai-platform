<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->index('team_id');
            $table->string('connection_method', 32)->default('own_app');
            $table->string('business_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->string('ban_state', 32)->nullable();
            $table->string('review_status', 32)->nullable();
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->foreignId('whatsapp_account_id')
                ->nullable()
                ->constrained('whatsapp_accounts')
                ->cascadeOnDelete();
            $table->index('whatsapp_account_id');
            $table->string('name_status')->nullable();
            $table->string('messaging_limit_tier')->nullable();
            $table->text('registration_pin')->nullable();
            $table->timestamp('registered_at')->nullable();
        });

        DB::table('whatsapp_accounts')
            ->select(['id', 'whatsapp_phone_number_id', 'method', 'app_id', 'app_secret', 'verify_token'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $teamId = DB::table('whatsapp_phone_numbers')
                        ->join('channels', 'channels.id', '=', 'whatsapp_phone_numbers.channel_id')
                        ->where('whatsapp_phone_numbers.id', $account->whatsapp_phone_number_id)
                        ->value('channels.team_id');

                    if ($teamId === null) {
                        throw new RuntimeException('A Conta do WhatsApp não possui Empresa associada.');
                    }

                    $isTechProvider = $account->method === 'embedded_signup';
                    $attributes = [
                        'team_id' => $teamId,
                        'connection_method' => $isTechProvider ? 'tech_provider' : 'own_app',
                    ];

                    if ($isTechProvider) {
                        $attributes['app_id'] = null;
                        $attributes['app_secret'] = null;
                        $attributes['verify_token'] = null;
                        $attributes['webhook_verified_at'] = null;
                    }

                    DB::table('whatsapp_accounts')
                        ->where('id', $account->id)
                        ->update($attributes);

                    DB::table('whatsapp_phone_numbers')
                        ->where('id', $account->whatsapp_phone_number_id)
                        ->update(['whatsapp_account_id' => $account->id]);
                }
            });

        DB::table('whatsapp_phone_numbers')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('whatsapp_accounts')
                    ->whereColumn('whatsapp_accounts.whatsapp_phone_number_id', 'whatsapp_phone_numbers.id');
            })
            ->delete();

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable(false)->change();
            $table->text('app_secret')->nullable()->change();
            $table->string('verify_token')->nullable()->change();
            $table->unique('waba_id');
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->foreignId('whatsapp_account_id')->nullable(false)->change();
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_phone_number_id']);
            $table->dropConstrainedForeignId('whatsapp_phone_number_id');
            $table->dropColumn('method');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'type']);
            $table->unique(['team_id', 'type']);
        });
    }

    public function down(): void
    {
        $hasMultipleNumbers = DB::table('whatsapp_phone_numbers')
            ->select('whatsapp_account_id')
            ->groupBy('whatsapp_account_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $hasAccountWithoutNumber = DB::table('whatsapp_accounts')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('whatsapp_phone_numbers')
                    ->whereColumn('whatsapp_phone_numbers.whatsapp_account_id', 'whatsapp_accounts.id');
            })
            ->exists();
        $hasUnrecoverableCredentials = DB::table('whatsapp_accounts')
            ->whereNull('app_secret')
            ->orWhereNull('verify_token')
            ->exists();
        $hasNewAccountState = DB::table('whatsapp_accounts')
            ->whereNotNull('subscribed_at')
            ->orWhereNotNull('business_id')
            ->orWhereNotNull('name')
            ->orWhereNotNull('ban_state')
            ->orWhereNotNull('review_status')
            ->exists();
        $hasNewPhoneState = DB::table('whatsapp_phone_numbers')
            ->whereNotNull('name_status')
            ->orWhereNotNull('messaging_limit_tier')
            ->orWhereNotNull('registration_pin')
            ->orWhereNotNull('registered_at')
            ->exists();

        if ($hasMultipleNumbers || $hasAccountWithoutNumber || $hasUnrecoverableCredentials || $hasNewAccountState || $hasNewPhoneState) {
            throw new RuntimeException('A reestruturação da Conta do WhatsApp não pode ser revertida sem descartar dados.');
        }

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->foreignId('whatsapp_phone_number_id')->nullable();
            $table->string('method', 32)->default('manual');
        });

        $accounts = DB::table('whatsapp_accounts')->get(['id', 'connection_method']);

        foreach ($accounts as $account) {
            $phoneNumberId = DB::table('whatsapp_phone_numbers')
                ->where('whatsapp_account_id', $account->id)
                ->value('id');

            DB::table('whatsapp_accounts')
                ->where('id', $account->id)
                ->update([
                    'whatsapp_phone_number_id' => $phoneNumberId,
                    'method' => $account->connection_method === 'tech_provider' ? 'embedded_signup' : 'manual',
                ]);
        }

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->foreignId('whatsapp_phone_number_id')->nullable(false)->change();
            $table->unique('whatsapp_phone_number_id');
            $table->foreign('whatsapp_phone_number_id')
                ->references('id')
                ->on('whatsapp_phone_numbers')
                ->cascadeOnDelete();
            $table->dropUnique(['waba_id']);
            $table->dropIndex(['team_id']);
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn([
                'connection_method',
                'business_id',
                'name',
                'subscribed_at',
                'ban_state',
                'review_status',
            ]);
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->dropIndex(['whatsapp_account_id']);
            $table->dropConstrainedForeignId('whatsapp_account_id');
            $table->dropColumn([
                'name_status',
                'messaging_limit_tier',
                'registration_pin',
                'registered_at',
            ]);
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->text('app_secret')->nullable(false)->change();
            $table->string('verify_token')->nullable(false)->change();
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'type']);
            $table->index(['team_id', 'type']);
        });
    }
};
