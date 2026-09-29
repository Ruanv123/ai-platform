<?php

use App\Enums\ChannelType;
use App\Enums\WhatsAppConnectionMethod;
use App\Models\Channel;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('stores an Empresa-owned account and resolves its numbers through the WABA', function () {
    $team = Team::factory()->create();
    $account = WhatsAppAccount::factory()->for($team, 'team')->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsApp]);
    $phoneNumber = WhatsAppPhoneNumber::factory()
        ->for($channel)
        ->for($account, 'whatsappAccount')
        ->create([
            'registration_pin' => '123456',
            'registered_at' => now(),
        ]);

    expect($account->fresh()->team->is($team))->toBeTrue()
        ->and($account->fresh()->phoneNumbers->first()->is($phoneNumber))->toBeTrue()
        ->and($phoneNumber->fresh()->whatsappAccount->is($account))->toBeTrue()
        ->and($phoneNumber->fresh()->registration_pin)->toBe('123456')
        ->and($phoneNumber->getRawOriginal('registration_pin'))->not->toBe('123456')
        ->and($phoneNumber->fresh()->toArray())->not->toHaveKey('registration_pin');
});

it('stores the connection and phone metadata columns on their owning records', function () {
    $account = WhatsAppAccount::factory()->techProvider()->create();
    $phoneNumber = WhatsAppPhoneNumber::factory()->create();

    expect(Schema::hasColumns('whatsapp_accounts', [
        'team_id',
        'connection_method',
        'business_id',
        'name',
        'app_id',
        'subscribed_at',
        'ban_state',
        'review_status',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('whatsapp_accounts', 'whatsapp_phone_number_id'))->toBeFalse()
        ->and(Schema::hasColumns('whatsapp_phone_numbers', [
            'whatsapp_account_id',
            'name_status',
            'messaging_limit_tier',
            'registration_pin',
            'registered_at',
        ]))->toBeTrue()
        ->and($account->connection_method)->toBe(WhatsAppConnectionMethod::TechProvider)
        ->and($account->app_secret)->toBeNull()
        ->and($account->verify_token)->toBeNull()
        ->and($phoneNumber->whatsappAccount)->not->toBeNull();
});

it('keeps WABA IDs unique', function () {
    $account = WhatsAppAccount::factory()->create();

    expect(fn () => WhatsAppAccount::factory()->for($account->team, 'team')->create([
        'waba_id' => $account->waba_id,
    ]))->toThrow(QueryException::class);
});

it('keeps non-null verify tokens unique', function () {
    $account = WhatsAppAccount::factory()->create(['verify_token' => 'shared-verify-token']);

    expect(fn () => WhatsAppAccount::factory()->for($account->team, 'team')->create([
        'verify_token' => 'shared-verify-token',
    ]))->toThrow(QueryException::class);
});
