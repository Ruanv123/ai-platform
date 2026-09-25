<?php

use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createTemplateWhatsAppChannel(User $user, array $channelAttributes = []): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsApp,
        ...$channelAttributes,
    ]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create();
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create();

    return [$channel, $phoneNumber, $account];
}

it('redirects guests to login when they visit templates', function () {
    $this->get(route('templates.index'))->assertRedirect(route('login'));
});

it('syncs Meta templates idempotently and marks removed templates', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::fakeSequence()
        ->push([
            'data' => [[
                'id' => 'meta-123',
                'name' => 'consulta_amanha',
                'language' => 'pt_BR',
                'status' => 'APPROVED',
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Olá {{1}}, sua consulta é amanhã.'],
                    ['type' => 'FOOTER', 'text' => 'Até breve'],
                ],
            ]],
        ])
        ->push(['data' => []]);

    $first = $this->actingAs($user)->post(route('templates.sync'));
    $second = $this->actingAs($user)->post(route('templates.sync'));

    $first->assertRedirect(route('templates.index'));
    $second->assertRedirect(route('templates.index'));
    expect(WhatsAppTemplate::query()->where('whatsapp_account_id', $account->id)->count())->toBe(1);
    $template = WhatsAppTemplate::query()->firstOrFail();
    expect($template->status->value)->toBe('deleted')
        ->and($template->body)->toBe('Olá {{1}}, sua consulta é amanhã.')
        ->and($template->variables[0]['position'])->toBe(1);
    expect($template->history()->count())->toBe(2);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), $endpoint));
});

it('renders only templates from the current team and exposes the approval explanation', function () {
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->rejected('INVALID_FORMAT')->create([
        'rejection_explanation' => 'Revise as variáveis antes de reenviar.',
    ]);
    $otherUser = User::factory()->withTeam()->create();
    [, , $otherAccount] = createTemplateWhatsAppChannel($otherUser);
    $otherTemplate = WhatsAppTemplate::factory()->for($otherAccount, 'account')->create();

    $response = $this->actingAs($user)->get(route('templates.index'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('templates/index')
        ->where('templates.data.0.id', $template->id)
        ->where('templates.data.0.status', 'rejected')
        ->missing('templates.data.1')
    );

    $this->actingAs($user)->get(route('templates.show', $template))
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/show')
            ->where('template.rejectionExplanation', 'Revise as variáveis antes de reenviar.')
        );
    $this->actingAs($user)->get(route('templates.show', $otherTemplate))->assertNotFound();
});

it('preserves a draft when Meta submission fails and moves it to pending on success', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [, , $account] = createTemplateWhatsAppChannel($user);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create([
        'name' => 'lembrete_consulta',
        'body' => 'Olá {{1}}, seu horário é amanhã.',
        'variables' => [['position' => 1, 'component' => 'body']],
        'requested_category' => 'utility',
    ]);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$account->waba_id.'/message_templates';
    Http::fakeSequence('*')
        ->pushFailedConnection()
        ->push(['id' => 'meta-new', 'status' => 'PENDING', 'category' => 'UTILITY']);

    $failed = $this->actingAs($user)->post(route('templates.submit', $template));

    $failed->assertSessionHasErrors('template');
    expect($template->fresh()->status->value)->toBe('draft');

    $submitted = $this->actingAs($user)->post(route('templates.submit', $template));

    $submitted->assertRedirect(route('templates.show', $template));
    expect($template->fresh()->status->value)->toBe('pending')
        ->and($template->fresh()->meta_template_id)->toBe('meta-new');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === $endpoint);
});

it('forbids a member from submitting a template', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    [, , $account] = createTemplateWhatsAppChannel($owner);
    $template = WhatsAppTemplate::factory()->for($account, 'account')->draft()->create();

    $this->actingAs($member)->post(route('templates.submit', $template))->assertForbidden();
});
