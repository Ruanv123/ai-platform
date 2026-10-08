<?php

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function libraryTemplateAccount(User $user): WhatsAppAccount
{
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create();
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();

    return $account;
}

it('offers ready-made models and remembers which one a template came from', function () {
    $user = User::factory()->withTeam()->create();
    $account = libraryTemplateAccount($user);

    $this->actingAs($user)->get(route('templates.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/form')
            ->where('library.1.key', 'lembrete_compromisso')
            ->where('library.1.usage', 'appointment_reminder')
            ->where('library.1.variables.1.description', 'Data do compromisso, no formato dia/mês'));

    $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'lembrete_compromisso',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'format' => 'standard',
        'library_key' => 'lembrete_compromisso',
        'body' => 'Olá, {{1}}!',
        'variables' => [['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato']],
    ])->assertCreated()->assertJsonPath('libraryKey', 'lembrete_compromisso');

    $this->actingAs($user)->postJson(route('templates.store'), [
        'whatsapp_account_id' => $account->getKey(),
        'name' => 'modelo_inexistente',
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'format' => 'standard',
        'library_key' => 'modelo_inexistente',
        'body' => 'Olá!',
    ])->assertJsonValidationErrors('library_key');
});

it('keeps variable descriptions when the template is edited or synchronized with Meta', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $account = libraryTemplateAccount($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create(['meta_template_id' => 'meta-description']);
    $payload = [
        'whatsapp_account_id' => $account->getKey(),
        'name' => $template->name,
        'language' => 'pt_BR',
        'requested_category' => 'utility',
        'body' => 'Olá {{1}}, seu atendimento está confirmado.',
    ];

    $this->actingAs($user)->putJson(route('templates.update', $template), [
        ...$payload,
        'variables' => [['position' => 1, 'component' => 'body', 'example' => 'Ana', 'description' => 'Primeiro nome do Contato']],
    ])->assertOk()->assertJsonPath('variables.0.description', 'Primeiro nome do Contato');

    $this->actingAs($user)->putJson(route('templates.update', $template), [...$payload, 'footer' => 'Até breve'])
        ->assertOk()->assertJsonPath('variables.0.description', 'Primeiro nome do Contato');

    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [[
        'id' => 'meta-description',
        'name' => $template->name,
        'language' => 'pt_BR',
        'status' => 'APPROVED',
        'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => 'Olá {{1}}, seu atendimento está confirmado.', 'example' => ['body_text' => [['Ana']]]]],
    ]]])]);
    $this->actingAs($user)->post(route('templates.sync'))->assertRedirect(route('templates.index'));

    expect($template->fresh()->variableDefinitions()[0]['description'])->toBe('Primeiro nome do Contato');
});
