<?php

use App\Enums\ChannelStatus;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppProfileTextChannel(User $user, ?string $lastError = 'Erro anterior preservado.'): Channel
{
    $channel = Channel::factory()->for($user->company())->create([
        'status' => ChannelStatus::Connected,
        'last_error' => $lastError,
    ]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create([
        'connection_method' => 'own_app',
        'waba_id' => '102030405060708',
        'app_id' => '987654321012345',
        'access_token' => 'profile-access-token',
        'app_secret' => 'profile-app-secret',
        'verify_token' => 'profile-verify-token',
    ]);
    WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create([
        'phone_number_id' => '15551234567890',
    ]);

    return $channel;
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
    $this->withoutVite();
});

it('sends only changed profile texts to Meta and preserves the connection state', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890/whatsapp_business_profile' => Http::response(['success' => true]),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileTextChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.texts', $channel),
        [
            'description' => 'Atendimento personalizado',
            'email' => '',
            'category' => 'RETAIL',
        ],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Perfil do WhatsApp atualizado com sucesso.',
        ]);
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/15551234567890/whatsapp_business_profile')
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer profile-access-token')
        && $request->data() === [
            'messaging_product' => 'whatsapp',
            'description' => 'Atendimento personalizado',
            'email' => '',
            'vertical' => 'RETAIL',
        ]
    );
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.')
        ->and($channel->whatsappPhoneNumber->whatsappAccount->app_id)->toBe('987654321012345');
});

it('allows an admin to update the WhatsApp profile', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890/whatsapp_business_profile' => Http::response(['success' => true]),
    ]);
    $owner = User::factory()->withTeam()->create();
    $admin = User::factory()->create();
    $owner->company()->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($owner->company());
    $channel = createWhatsAppProfileTextChannel($admin);

    $response = $this->actingAs($admin)->post(
        route('channels.whatsapp.profile.texts', $channel),
        ['about' => 'Aberto todos os dias'],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    Http::assertSent(fn (ClientRequest $request): bool => $request->data() === [
        'messaging_product' => 'whatsapp',
        'about' => 'Aberto todos os dias',
    ]);
});

it('returns a translated error when Meta rejects the update without changing the channel', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890/whatsapp_business_profile' => Http::response([
            'error' => ['code' => 100, 'type' => 'GraphMethodException'],
        ], 400),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileTextChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.texts', $channel),
        ['description' => 'Nova descrição'],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O Phone Number ID ou os campos informados são inválidos.',
        ]);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('forbids a Member from changing the WhatsApp profile', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $channel = createWhatsAppProfileTextChannel($owner);

    $response = $this->actingAs($member)->post(
        route('channels.whatsapp.profile.texts', $channel),
        ['about' => 'Novo recado'],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
});

it('returns not found when another company submits a profile update', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileTextChannel(User::factory()->withTeam()->create());

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.texts', $channel),
        ['about' => 'Novo recado'],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('returns validation messages for text fields outside Meta limits', function (array $payload, string $field, string $message) {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileTextChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.texts', $channel),
        $payload,
    );

    $response->assertSessionHasErrors([$field => $message]);
    Http::assertNothingSent();
})->with([
    'recado acima do limite' => [['about' => str_repeat('a', 140)], 'about', 'O recado pode ter no máximo 139 caracteres.'],
    'recado vazio' => [['about' => ''], 'about', 'O recado precisa ter pelo menos 1 caractere.'],
    'descrição acima do limite' => [['description' => str_repeat('a', 513)], 'description', 'A descrição pode ter no máximo 512 caracteres.'],
    'endereço acima do limite' => [['address' => str_repeat('a', 257)], 'address', 'O endereço pode ter no máximo 256 caracteres.'],
    'e-mail inválido' => [['email' => 'invalido'], 'email', 'Informe um e-mail válido.'],
    'e-mail acima do limite' => [['email' => str_repeat('a', 123).'@x.com'], 'email', 'O e-mail pode ter no máximo 128 caracteres.'],
    'mais de dois sites' => [['websites' => ['https://one.example', 'https://two.example', 'https://three.example']], 'websites', 'Informe no máximo dois sites.'],
    'site acima do limite' => [['websites' => ['https://example.test/'.str_repeat('a', 237)]], 'websites.0', 'Cada site pode ter no máximo 256 caracteres.'],
    'site sem http ou https' => [['websites' => ['ftp://example.test']], 'websites.0', 'Informe um endereço de site válido com http ou https.'],
    'categoria fora da lista' => [['category' => 'NOT_A_CATEGORY'], 'category', 'Selecione uma categoria válida.'],
    'nenhum campo alterado' => [[], 'about', 'Altere pelo menos um campo do Perfil antes de salvar.'],
]);

it('renders Portuguese labels for the Meta profile categories', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)->get(route('channels.whatsapp.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('whatsappProfileCategories.0.value', 'OTHER')
            ->where('whatsappProfileCategories.0.label', 'Outro')
            ->where('whatsappProfileCategories.14.value', 'RETAIL')
            ->where('whatsappProfileCategories.14.label', 'Varejo')
        );
});
