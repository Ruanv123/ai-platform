<?php

use App\Enums\ChannelType;
use App\Jobs\RespondToWhatsAppMessage;
use App\Jobs\RunFlowTurn;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use App\WhatsApp\OptOutInterceptor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('intercepts an opt-out before dispatching any flow job', function () {
    Http::preventStrayRequests();
    Queue::fake([RunFlowTurn::class, RespondToWhatsAppMessage::class]);
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $phone = WhatsAppPhoneNumber::factory()->for($channel)->create(['phone_number_id' => 'opt-out-phone']);
    WhatsAppAccount::factory()->for($phone, 'phoneNumber')->create();
    $contact = Contact::factory()->for($user->company())->create(['phone' => '5511999999999']);
    $message = Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'SAIR']);
    Http::fake([
        'https://graph.facebook.com/*/opt-out-phone/messages' => Http::response(['messages' => [['id' => 'confirmation-1']]]),
    ]);

    app(IncomingMessageResponseDispatcher::class)->dispatch($channel, $message);

    $message->refresh();
    expect($message->is_responded)->toBeTrue();
    $this->assertDatabaseHas('contact_consents', [
        'contact_id' => $contact->id,
        'purpose' => 'marketing',
        'action' => 'revoked',
        'source' => 'keyword',
    ]);
    $this->assertDatabaseHas('messages', [
        'contact_id' => $contact->id,
        'direction' => 'outgoing',
        'content' => 'Você foi descadastrado dos nossos envios de marketing e não receberá mais esse tipo de mensagem. Para voltar a receber, fale com a nossa equipe.',
        'delivery_status' => 'sent',
    ]);
    Queue::assertNotPushed(RunFlowTurn::class);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);
});

it('recognizes configurable opt-out variations', function (string $keyword) {
    config(['whatsapp.opt_out_keywords' => ['descadastrar']]);
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsAppTest]);
    $contact = Contact::factory()->for($user->company())->create();
    $message = Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => $keyword]);

    expect(app(OptOutInterceptor::class)->intercept($channel, $message))->toBeTrue();
    expect($message->fresh()->is_responded)->toBeTrue();
})->with([
    'descadastrar',
    'quero descadastrar',
]);
