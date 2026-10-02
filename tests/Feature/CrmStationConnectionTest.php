<?php

use App\Crm\Adapters\RdStationCrmAdapter;
use App\Crm\CrmErrorCategory;
use App\Crm\CrmProviderException;
use App\Enums\TeamRole;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function rdStationFixture(string $name): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/'.$name.'.json')), true, flags: JSON_THROW_ON_ERROR);
}

function fakeRdStationServer(): void
{
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $queryString = (string) parse_url($request->url(), PHP_URL_QUERY);

        if ($path === '/oauth2/token') {
            return Http::response(rdStationFixture($request->data()['grant_type'] === 'refresh_token' ? 'refresh-token' : 'token'));
        }

        $fixture = match ($path) {
            '/crm/v2/pipelines' => str_contains($queryString, 'number]=2') || str_contains($queryString, 'number%5D=2')
                ? 'pipelines-page-2'
                : 'pipelines',
            '/crm/v2/pipelines/pipeline-1/stages' => 'stages',
            '/crm/v2/pipelines/pipeline-2/stages' => 'stages-page-2',
            '/crm/v2/custom_fields' => 'fields',
            '/crm/v2/users' => 'users',
            '/crm/v2/lost_reasons' => 'lost-reasons',
            '/crm/v2/teams' => 'teams',
            default => null,
        };

        if ($fixture === null) {
            throw new RuntimeException('Unexpected RD Station request: '.$request->url());
        }

        return Http::response(rdStationFixture($fixture));
    });
    Http::preventStrayRequests();
}

beforeEach(function () {
    config()->set('services.rd_station_crm', [
        'client_id' => 'rd-client-id',
        'client_secret' => 'rd-client-secret',
        'redirect' => 'http://localhost/integrations/crm/rd-station/callback',
    ]);
});

it('starts RD OAuth with state bound to the current company', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.crm.rd-station.connect'));

    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['client_id'])->toBe('rd-client-id')
        ->and($query['redirect_uri'])->toBe('http://localhost/integrations/crm/rd-station/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['state'])->toHaveLength(64);
    $this->assertDatabaseCount('integrations', 0);
});

it('does not persist an authorization refusal or invalid state', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->get(route('integrations.crm.rd-station.callback', ['state' => 'unknown', 'code' => 'unused']))
        ->assertRedirect(route('integrations.show', 'crm'))
        ->assertSessionHas('crm_error');

    $this->assertDatabaseCount('integrations', 0);

    $connect = $this->get(route('integrations.crm.rd-station.connect'));
    parse_str((string) parse_url($connect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->get(route('integrations.crm.rd-station.callback', ['state' => $query['state'], 'error' => 'access_denied']))
        ->assertRedirect(route('integrations.show', 'crm'))
        ->assertSessionHas('crm_error');
    $this->assertDatabaseCount('integrations', 0);
});

it('connects RD Station, loads metadata, and reconnects the same company CRM', function () {
    fakeRdStationServer();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    $this->actingAs($user);

    foreach (['authorization-code-one', 'authorization-code-two'] as $code) {
        $connect = $this->get(route('integrations.crm.rd-station.connect'));
        parse_str((string) parse_url($connect->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get(route('integrations.crm.rd-station.callback', ['state' => $query['state'], 'code' => $code]))
            ->assertRedirect(route('integrations.show', 'crm'))
            ->assertSessionHas('crm_success');
    }

    $pipelineUrls = Http::recorded()->map(fn (array $record): string => $record[0]->url())
        ->filter(fn (string $url): bool => str_contains($url, '/pipelines'))
        ->values()
        ->all();
    expect(collect($pipelineUrls)->contains(fn (string $url): bool => str_contains($url, 'page%5Bnumber%5D=2')))->toBeTrue();

    $integration = $user->company()->integrations()->where('type', 'crm')->firstOrFail();
    expect($user->company()->integrations()->where('type', 'crm')->count())->toBe(1)
        ->and($integration->provider)->toBe('rd_station')
        ->and($integration->status)->toBe('connected')
        ->and($integration->credential('access_token'))->toBe('rd-access-token')
        ->and($integration->crm_metadata['pipelines'][1]['id'])->toBe('pipeline-2')
        ->and($integration->crm_metadata['pipelines'][0]['stages'][0]['id'])->toBe('stage-1')
        ->and($integration->crm_metadata['fields'][0]['options'])->toBe(['Site', 'Indicação'])
        ->and($integration->crm_metadata['owners'][0]['id'])->toBe('user-1')
        ->and($integration->crm_metadata['lost_reasons'][0]['id'])->toBe('reason-1')
        ->and($integration->crm_metadata['account']['teams'][0])->toBe(['id' => 'team-1', 'name' => 'Empresa RD'])
        ->and($integration->crm_metadata_loaded_at)->not->toBeNull()
        ->and($integration->getRawOriginal('credentials'))->not->toContain('rd-access-token');
});

it('reloads CRM metadata when the connection page opens', function () {
    fakeRdStationServer();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'rd-access-token',
            'refresh_token' => 'rd-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ]);

    $this->actingAs($user)->get(route('integrations.show', 'crm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('integration.id', $integration->getKey())
            ->where('integration.crmAccount.teams.0.id', 'team-1')
            ->where('integration.crmAccount.teams.0.name', 'Empresa RD')
            ->where('integration.crmMetadataLoadedAt', fn ($value) => is_string($value))
        );

    expect($integration->fresh()->crm_metadata['pipelines'][0]['id'])->toBe('pipeline-1');
});

it('conforms to the canonical metadata shape from provider fixtures', function (string $adapterClass) {
    fakeRdStationServer();
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'credentials' => [
            'access_token' => 'rd-access-token',
            'refresh_token' => 'rd-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ],
    ]);

    $metadata = app($adapterClass)->loadMetadata($integration);

    expect(array_keys($metadata))->toBe(['account', 'pipelines', 'fields', 'owners', 'lost_reasons'])
        ->and($metadata['account']['teams'][0])->toBe(['id' => 'team-1', 'name' => 'Empresa RD'])
        ->and($metadata['pipelines'][0]['stages'][0])->toMatchArray([
            'id' => 'stage-1',
            'name' => 'Qualificação',
            'status' => null,
            'position' => 1,
        ])
        ->and($metadata['fields'][0])->toMatchArray([
            'id' => 'field-1',
            'name' => 'Origem',
            'type' => 'select',
            'options' => ['Site', 'Indicação'],
        ])
        ->and(array_keys(app($adapterClass)->capabilities()))->toBe([
            'metadata',
            'loss_reason_required',
            'won_and_lost_stages',
            'webhooks',
            'operations',
        ])
        ->and(app($adapterClass)->capabilities()['operations'])->toBe([
            'lookup',
            'person_create',
            'person_update',
            'deal_create',
            'deal_update',
        ])
        ->and(app($adapterClass)->rateLimit())->toBe(['requests' => 120, 'window_seconds' => 60]);
})->with([
    'RD Station CRM' => [RdStationCrmAdapter::class],
]);

it('skips metadata refresh when it was loaded within the last five minutes', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => ['access_token' => 'rd-access-token', 'expires_at' => now()->addHour()->timestamp],
        'crm_metadata' => ['pipelines' => []],
        'crm_metadata_loaded_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->get(route('integrations.show', 'crm'))->assertOk();

    Http::assertNothingSent();
});

it('refreshes a rotated token under a CRM lock and rereads credentials', function () {
    fakeRdStationServer();
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'credentials' => [
            'access_token' => 'expired-access',
            'refresh_token' => 'expired-refresh',
            'expires_at' => now()->subMinute()->timestamp,
        ],
    ]);
    $firstRead = $integration->fresh();
    $secondRead = $integration->fresh();
    $adapter = app(RdStationCrmAdapter::class);

    $adapter->freshIntegration($firstRead);
    $adapter->freshIntegration($secondRead);

    expect($integration->fresh()->credential('access_token'))->toBe('rd-next-access-token')
        ->and($integration->fresh()->credential('refresh_token'))->toBe('rd-next-refresh-token');
    Http::assertSentCount(1);
});

it('marks the CRM with an access error when RD refuses refresh', function () {
    Http::fake(['https://api.rd.services/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'credentials' => ['refresh_token' => 'revoked-refresh', 'expires_at' => now()->subMinute()->timestamp],
    ]);

    try {
        app(RdStationCrmAdapter::class)->freshIntegration($integration);
        $this->fail('Expected access denial.');
    } catch (CrmProviderException $exception) {
        expect($exception->category)->toBe(CrmErrorCategory::AccessDenied);
    }

    expect($integration->fresh()->status)->toBe('error');
});

it('keeps the CRM connected when the refresh endpoint rate limits', function () {
    Http::fake(['https://api.rd.services/oauth2/token' => Http::response(['message' => 'Rate limited'], 429, ['Retry-After' => '30'])]);
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => ['refresh_token' => 'active-refresh', 'expires_at' => now()->subMinute()->timestamp],
    ]);

    try {
        app(RdStationCrmAdapter::class)->freshIntegration($integration);
        $this->fail('Expected a rate limit error.');
    } catch (CrmProviderException $exception) {
        expect($exception->category)->toBe(CrmErrorCategory::RateLimit)
            ->and($exception->retryAfterSeconds)->toBe(30);
    }

    expect($integration->fresh()->status)->toBe('connected');
});

it('forbids Members from connecting or disconnecting the CRM', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $integration = Integration::factory()->for($owner->company())->create(['type' => 'crm', 'provider' => 'rd_station']);

    $this->actingAs($member)->get(route('integrations.crm.rd-station.connect'))->assertForbidden();
    $this->delete(route('integrations.destroy', $integration))->assertForbidden();
});
