<?php

use App\Enums\ChannelType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Database\QueryException;

it('keeps contacts unique by phone within an Empresa', function () {
    $team = Team::factory()->create();

    Contact::factory()->for($team)->create(['phone' => '5511999999999']);

    expect(fn () => Contact::factory()->for($team)->create(['phone' => '5511999999999']))
        ->toThrow(QueryException::class);
});

it('stores the contact phone as E.164', function () {
    $team = Team::factory()->create();

    $contact = Contact::factory()->for($team)->create(['phone' => '55 (31) 8644-1470']);

    expect($contact->fresh()->phone)->toBe('+5531986441470')
        ->and(fn () => Contact::factory()->for($team)->create(['phone' => '5531986441470']))
        ->toThrow(QueryException::class);
});

it('stores message direction, delivery status and response state through its factory states', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create();

    $incoming = Message::factory()
        ->for($contact)
        ->for($channel)
        ->incoming()
        ->unresponded()
        ->create();
    $outgoing = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->responded()
        ->create();

    expect($incoming->direction)->toBe(MessageDirection::Incoming)
        ->and($incoming->delivery_status)->toBeNull()
        ->and($incoming->is_responded)->toBeFalse()
        ->and($outgoing->direction)->toBe(MessageDirection::Outgoing)
        ->and($outgoing->delivery_status)->toBe(MessageDeliveryStatus::Pending)
        ->and($outgoing->is_responded)->toBeTrue();
});

it('returns only the current channel history after the first gap longer than 24 hours', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create();
    $otherChannel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WebsiteChat,
    ]);

    $beforeGap = Message::factory()->for($contact)->for($channel)->create([
        'created_at' => '2026-01-01 08:00:00',
    ]);
    $afterGap = Message::factory()->for($contact)->for($channel)->create([
        'created_at' => '2026-01-02 09:00:00',
    ]);
    $latest = Message::factory()->for($contact)->for($channel)->create([
        'created_at' => '2026-01-02 10:00:00',
    ]);
    $otherChannelMessage = Message::factory()->for($contact)->for($otherChannel)->create([
        'created_at' => '2026-01-02 11:00:00',
    ]);

    $history = $contact->history($channel);

    expect($history->modelKeys())->toBe([$afterGap->id, $latest->id])
        ->and($history->modelKeys())->not->toContain($beforeGap->id)
        ->and($history->modelKeys())->not->toContain($otherChannelMessage->id);
});

it('limits the current channel history to 50 messages by default', function () {
    $team = Team::factory()->create();
    $contact = Contact::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create();

    $messages = collect(range(1, 51))->map(function (int $number) use ($contact, $channel): Message {
        return Message::factory()->for($contact)->for($channel)->create([
            'created_at' => now()->subMinutes(51 - $number),
        ]);
    });

    $history = $contact->history($channel);

    expect($history)->toHaveCount(50)
        ->and($history->first()->is($messages->get(1)))->toBeTrue()
        ->and($history->last()->is($messages->last()))->toBeTrue();
});
