<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function companyUpdatePayload(): array
{
    return [
        'name' => 'Acme Atendimento',
        'segment' => 'tecnologia',
        'website' => 'https://acme.example.com',
        'business_description' => 'Atendimento automatizado para empresas.',
        'products_services' => 'Chatbots e consultoria.',
        'target_audience' => 'Pequenas e médias empresas.',
        'phone' => '(11) 99999-9999',
        'public_email' => 'contato@acme.example.com',
        'address' => 'São Paulo, SP',
        'brand_voice' => 'Acolhedora e direta.',
        'brand_voice_examples' => ['Olá! Que bom ter você por aqui.', 'Pode contar com a gente.'],
    ];
}

test('company settings render only company props', function () {
    $team = Team::factory()->create([
        'name' => 'Acme Atendimento',
        'segment' => 'tecnologia',
        'website' => 'https://acme.example.com',
        'business_description' => 'Atendimento automatizado para empresas.',
        'products_services' => 'Chatbots e consultoria.',
        'target_audience' => 'Pequenas e médias empresas.',
        'phone' => '(11) 99999-9999',
        'public_email' => 'contato@acme.example.com',
        'address' => 'São Paulo, SP',
        'brand_voice' => 'Acolhedora e direta.',
        'brand_voice_examples' => ['Olá! Que bom ter você por aqui.', 'Pode contar com a gente.'],
        'business_hours' => [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
        ],
        'timezone' => 'America/Sao_Paulo',
    ]);
    Team::factory()->create(['brand_voice' => 'Não deve aparecer para outra Empresa.']);
    $user = User::factory()->withTeam($team)->create();

    $response = $this
        ->actingAs($user)
        ->get(route('company.edit'));

    $response->assertOk()
        ->assertDontSee('Não deve aparecer para outra Empresa.')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/company')
            ->where('team.id', $team->id)
            ->where('team.name', 'Acme Atendimento')
            ->where('team.segment', 'tecnologia')
            ->where('team.website', 'https://acme.example.com')
            ->where('team.businessDescription', 'Atendimento automatizado para empresas.')
            ->where('team.productsServices', 'Chatbots e consultoria.')
            ->where('team.targetAudience', 'Pequenas e médias empresas.')
            ->where('team.phone', '(11) 99999-9999')
            ->where('team.publicEmail', 'contato@acme.example.com')
            ->where('team.address', 'São Paulo, SP')
            ->where('team.brandVoice', 'Acolhedora e direta.')
            ->where('team.brandVoiceExamples', ['Olá! Que bom ter você por aqui.', 'Pode contar com a gente.'])
            ->where('team.agentPauseDurationMinutes', 30)
            ->has('segments')
            ->where('canUpdate', true)
            ->missing('team.slug')
            ->missing('team.businessHours')
            ->missing('team.timezone')
            ->missing('members')
            ->missing('invitations')
            ->missing('timezones')
            ->missing('weekdays'),
        );
});

test('owners can update all company details without changing business hours', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $team->update([
        'business_hours' => [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
        ],
        'timezone' => 'America/Sao_Paulo',
    ]);
    $originalBusinessHours = $team->fresh()->business_hours;

    $payload = companyUpdatePayload();
    $payload['name'] = 'Acme do Owner';
    $payload['agent_pause_duration_hours'] = 2;
    $payload['agent_pause_duration_minutes_part'] = 15;

    $response = $this
        ->actingAs($owner)
        ->patch(route('company.update'), $payload);

    $response
        ->assertRedirect(route('company.edit'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Dados da Empresa atualizados.',
        ]);

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Acme do Owner',
        'segment' => $payload['segment'],
        'website' => $payload['website'],
        'business_description' => $payload['business_description'],
        'products_services' => $payload['products_services'],
        'target_audience' => $payload['target_audience'],
        'phone' => $payload['phone'],
        'public_email' => $payload['public_email'],
        'address' => $payload['address'],
        'brand_voice' => $payload['brand_voice'],
        'agent_pause_duration_minutes' => 135,
    ]);
    expect($team->fresh()->business_hours)->toBe($originalBusinessHours);
    expect($team->fresh()->timezone)->toBe('America/Sao_Paulo')
        ->and($team->fresh()->brand_voice_examples)->toBe($payload['brand_voice_examples']);
});

test('admins can update all company details', function () {
    $owner = User::factory()->withTeam()->create();
    $admin = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $payload = companyUpdatePayload();
    $payload['name'] = 'Acme do Admin';
    $payload['agent_pause_duration_hours'] = 0;
    $payload['agent_pause_duration_minutes_part'] = 1;

    $response = $this
        ->actingAs($admin)
        ->patch(route('company.update'), $payload);

    $response->assertRedirect(route('company.edit'));
    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Acme do Admin',
        'public_email' => $payload['public_email'],
        'brand_voice' => $payload['brand_voice'],
        'agent_pause_duration_minutes' => 1,
    ]);
    expect($team->fresh()->brand_voice_examples)->toBe($payload['brand_voice_examples']);
});

it('validates the brand voice description and example phrase count', function (array $voice, string $errorField, string $message) {
    $user = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('company.edit'))
        ->patch(route('company.update'), array_merge(companyUpdatePayload(), $voice));

    $response->assertSessionHasErrors([$errorField => $message]);
})->with([
    'description over the limit' => [
        ['brand_voice' => str_repeat('a', 1001)],
        'brand_voice',
        'A descrição da Voz da marca deve ter no máximo 1.000 caracteres.',
    ],
    'only one example phrase' => [
        ['brand_voice_examples' => ['Uma frase de exemplo.']],
        'brand_voice_examples',
        'Informe de 2 a 4 frases de exemplo.',
    ],
    'more than four example phrases' => [
        ['brand_voice_examples' => ['Uma.', 'Duas.', 'Três.', 'Quatro.', 'Cinco.']],
        'brand_voice_examples',
        'Informe no máximo 4 frases de exemplo.',
    ],
]);

it('allows a voice description without example phrases', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->patch(route('company.update'), array_merge(companyUpdatePayload(), [
            'brand_voice' => 'Acolhedora e direta.',
            'brand_voice_examples' => ['', '', '', ''],
        ]))
        ->assertRedirect(route('company.edit'));

    expect($user->company()->fresh()->brand_voice)->toBe('Acolhedora e direta.')
        ->and($user->company()->fresh()->brand_voice_examples)->toBeNull();
});

it('rejects a pause duration outside the one-minute to twenty-four-hour range', function (int $hours, int $minutes, string $errorField) {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->from(route('company.edit'))
        ->patch(route('company.update'), array_merge(companyUpdatePayload(), [
            'agent_pause_duration_hours' => $hours,
            'agent_pause_duration_minutes_part' => $minutes,
        ]))
        ->assertSessionHasErrors($errorField);

    expect($user->company()->fresh()->agent_pause_duration_minutes)->toBe(30);
})->with([
    'zero minutes' => [0, 0, 'agent_pause_duration_minutes'],
    'more than one day' => [24, 1, 'agent_pause_duration_minutes'],
    'invalid minute component' => [0, 60, 'agent_pause_duration_minutes_part'],
]);

it('accepts the maximum twenty-four-hour pause duration', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->patch(route('company.update'), array_merge(companyUpdatePayload(), [
            'agent_pause_duration_hours' => 24,
            'agent_pause_duration_minutes_part' => 0,
        ]))
        ->assertRedirect(route('company.edit'));

    expect($user->company()->fresh()->agent_pause_duration_minutes)->toBe(1440);
});

test('company validation returns Portuguese messages for invalid fields', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('company.edit'))
        ->patch(route('company.update'), [
            'segment' => 'segmento-inexistente',
            'website' => 'site-invalido',
            'public_email' => 'email-invalido',
        ]);

    $response->assertSessionHasErrors([
        'segment' => 'Selecione um segmento válido.',
        'website' => 'Informe um site válido, começando por http:// ou https://.',
        'public_email' => 'Informe um e-mail público válido.',
    ]);
    expect($user->company()->fresh()->segment)->toBeNull();
});

test('company validation returns Portuguese messages for oversized optional fields', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('company.edit'))
        ->patch(route('company.update'), [
            'website' => 'https://example.com/'.str_repeat('a', 2048),
            'phone' => str_repeat('1', 41),
            'public_email' => str_repeat('a', 247).'@example.com',
            'address' => str_repeat('a', 1001),
        ]);

    $response->assertSessionHasErrors([
        'website' => 'O site deve ter no máximo 2.048 caracteres.',
        'phone' => 'O telefone público deve ter no máximo 40 caracteres.',
        'public_email' => 'O e-mail público deve ter no máximo 255 caracteres.',
        'address' => 'O endereço deve ter no máximo 1.000 caracteres.',
    ]);
});

test('members can read company settings without update permission', function () {
    $owner = User::factory()->withTeam()->create();
    $owner->company()->update([
        'brand_voice' => 'Acolhedora e direta.',
        'brand_voice_examples' => ['Olá! Que bom ter você por aqui.', 'Pode contar com a gente.'],
    ]);
    $member = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->get(route('company.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/company')
        ->where('canUpdate', false)
        ->where('team.name', $team->name)
        ->where('team.brandVoice', 'Acolhedora e direta.')
        ->where('team.brandVoiceExamples', ['Olá! Que bom ter você por aqui.', 'Pode contar com a gente.']),
    );
});

test('members cannot update company settings through a direct request', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $response = $this
        ->actingAs($member)
        ->patch(route('company.update'), [
            'name' => 'Nome proibido',
            'brand_voice' => 'Voz proibida.',
            'brand_voice_examples' => ['Não deve salvar.', 'Esta também não.'],
        ]);

    $response->assertForbidden();
    expect($team->fresh()->name)->not->toBe('Nome proibido')
        ->and($team->fresh()->brand_voice)->toBeNull();
});

test('company voice updates stay within the current company', function () {
    $owner = User::factory()->withTeam()->create();
    $otherTeam = Team::factory()->create(['brand_voice' => 'Voz de outra Empresa.']);

    $this->actingAs($owner)
        ->patch(route('company.update'), array_merge(companyUpdatePayload(), [
            'brand_voice' => 'Voz da minha Empresa.',
            'brand_voice_examples' => ['Exemplo um.', 'Exemplo dois.'],
        ]))
        ->assertRedirect(route('company.edit'));

    expect($owner->company()->fresh()->brand_voice)->toBe('Voz da minha Empresa.')
        ->and($otherTeam->fresh()->brand_voice)->toBe('Voz de outra Empresa.');
});

test('guests are redirected to login from every settings tab', function () {
    foreach ([
        'profile.edit',
        'security.edit',
        'appearance.edit',
        'company.edit',
        'team.edit',
        'business-hours.edit',
    ] as $routeName) {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }
});

test('users without a company are redirected to onboarding from every settings tab', function () {
    $user = User::factory()->create();

    foreach ([
        'profile.edit',
        'security.edit',
        'appearance.edit',
        'company.edit',
        'team.edit',
        'business-hours.edit',
    ] as $routeName) {
        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('onboarding.company'));
    }
});
