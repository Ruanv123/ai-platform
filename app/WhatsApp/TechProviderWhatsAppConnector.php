<?php

namespace App\WhatsApp;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\WhatsAppConnectionMethod;
use App\Exceptions\WhatsAppConnectionException;
use App\Models\Channel;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\WhatsApp\Oficial\MetaErrorTranslator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TechProviderWhatsAppConnector
{
    public function __construct(
        private readonly WhatsAppTemplateSynchronizer $templateSynchronizer,
        private readonly MetaErrorTranslator $errorTranslator,
    ) {}

    public function connect(Team $team, string $code): Channel
    {
        $appId = trim((string) config('services.whatsapp.platform.app_id'));
        $appSecret = trim((string) config('services.whatsapp.platform.app_secret'));

        if ($appId === '' || $appSecret === '') {
            throw new WhatsAppConnectionException('A conexão com a Meta não está configurada nesta plataforma.');
        }

        $accessToken = $this->exchangeCode($code, $appId, $appSecret);
        [$wabaId, $phoneNumberId] = $this->discoverAssets($accessToken, $appId, $appSecret);
        [$channel, $account, $phoneNumber] = $this->connectionRecords($team, $wabaId, $phoneNumberId, $accessToken);

        try {
            if ($account->subscribed_at === null) {
                $this->subscribeToWaba($account);
            }

            if ($phoneNumber->registered_at === null) {
                $this->registerPhoneNumber($phoneNumber, $account);
            }

            $this->syncPhoneNumberDetails($phoneNumber, $account);
            $this->templateSynchronizer->sync($account);
            $channel->update([
                'status' => ChannelStatus::Connected,
                'last_error' => null,
            ]);

            return $channel->refresh();
        } catch (WhatsAppConnectionException $exception) {
            $this->markConnectionAsFailed($channel, $exception->getMessage());

            throw $exception;
        } catch (ConnectionException) {
            $message = 'Não foi possível conectar à Meta. Verifique sua conexão e tente novamente.';
            $this->markConnectionAsFailed($channel, $message);

            throw new WhatsAppConnectionException($message);
        } catch (RequestException $exception) {
            $message = $this->translatedMetaError($exception->response);
            $this->markConnectionAsFailed($channel, $message);

            throw new WhatsAppConnectionException($message, previous: $exception);
        } catch (Throwable $exception) {
            report($exception);
            $message = 'Não foi possível concluir a conexão com a Meta. Tente novamente.';
            $this->markConnectionAsFailed($channel, $message);

            throw new WhatsAppConnectionException($message, previous: $exception);
        }
    }

    private function exchangeCode(string $code, string $appId, string $appSecret): string
    {
        $response = $this->graphRequest()->get($this->graphUrl('oauth/access_token'), [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ]);

        $this->throwIfMetaRejected($response);
        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new WhatsAppConnectionException('A Meta não retornou o token de acesso da conta.');
        }

        return $accessToken;
    }

    private function discoverAssets(string $accessToken, string $appId, string $appSecret): array
    {
        $response = $this->graphRequest($appId.'|'.$appSecret)->get(
            $this->graphUrl('debug_token'),
            ['input_token' => $accessToken],
        );

        $this->throwIfMetaRejected($response);
        $debugToken = $response->json('data');

        if (! is_array($debugToken)
            || ($debugToken['is_valid'] ?? false) !== true
            || (string) ($debugToken['app_id'] ?? '') !== $appId) {
            throw new WhatsAppConnectionException('A autorização da Meta é inválida ou pertence a outro aplicativo.');
        }

        $grants = is_array($debugToken['granular_scopes'] ?? null)
            ? $debugToken['granular_scopes']
            : [];
        $scopes = is_array($debugToken['scopes'] ?? null) ? $debugToken['scopes'] : [];
        $wabaIds = $this->targetIdsForScope($grants, 'whatsapp_business_management');
        $phoneIds = $this->targetIdsForScope($grants, 'whatsapp_business_messaging');

        if ($wabaIds === []
            || ! in_array('whatsapp_business_management', $scopes, true)
            || ! in_array('whatsapp_business_messaging', $scopes, true)) {
            throw new WhatsAppConnectionException('A autorização precisa incluir as permissões de gerenciamento e mensagens do WhatsApp.');
        }

        $phoneCandidates = [];
        $phoneNumbersByWaba = [];

        foreach ($wabaIds as $wabaId) {
            $phoneResponse = $this->graphRequest($accessToken)->get(
                $this->graphUrl(rawurlencode($wabaId).'/phone_numbers'),
                ['fields' => 'id', 'limit' => 100],
            );

            $this->throwIfMetaRejected($phoneResponse);
            $numbers = $phoneResponse->json('data');
            $phoneNumbersByWaba[$wabaId] = [];

            foreach (is_array($numbers) ? $numbers : [] as $number) {
                $phoneNumberId = is_array($number) ? (string) ($number['id'] ?? '') : '';

                if ($phoneNumberId === '') {
                    continue;
                }

                $phoneNumbersByWaba[$wabaId][] = $phoneNumberId;

                if (in_array($phoneNumberId, $phoneIds, true)) {
                    $phoneCandidates[] = [$wabaId, $phoneNumberId];
                }
            }
        }

        if (count($phoneCandidates) === 1) {
            return $phoneCandidates[0];
        }

        if (count($phoneCandidates) > 1) {
            throw new WhatsAppConnectionException('A Meta autorizou mais de um número. Conecte um número por vez.');
        }

        if (count($wabaIds) === 1) {
            $wabaId = $wabaIds[0];
            $numbers = $phoneNumbersByWaba[$wabaId] ?? [];

            if (count($numbers) === 1) {
                return [$wabaId, $numbers[0]];
            }
        }

        throw new WhatsAppConnectionException('Não foi possível identificar com segurança o número autorizado pela Meta.');
    }

    private function targetIdsForScope(array $grants, string $scope): array
    {
        $targetIds = [];

        foreach ($grants as $grant) {
            if (! is_array($grant) || ($grant['scope'] ?? null) !== $scope) {
                continue;
            }

            foreach (is_array($grant['target_ids'] ?? null) ? $grant['target_ids'] : [] as $targetId) {
                if (is_string($targetId) || is_int($targetId)) {
                    $targetIds[] = (string) $targetId;
                }
            }
        }

        return array_values(array_unique($targetIds));
    }

    private function connectionRecords(Team $team, string $wabaId, string $phoneNumberId, string $accessToken): array
    {
        $existingChannel = Channel::query()
            ->where('team_id', $team->getKey())
            ->where('type', ChannelType::WhatsApp)
            ->with('whatsappPhoneNumber.whatsappAccount')
            ->first();

        if ($existingChannel !== null) {
            $existingPhoneNumber = $existingChannel->whatsappPhoneNumber;
            $existingAccount = $existingPhoneNumber?->whatsappAccount;

            if ($existingPhoneNumber === null
                || $existingAccount?->connection_method !== WhatsAppConnectionMethod::TechProvider
                || $existingAccount->waba_id !== $wabaId
                || $existingPhoneNumber->phone_number_id !== $phoneNumberId) {
                throw new WhatsAppConnectionException('Esta Empresa já possui um Canal oficial do WhatsApp conectado.');
            }

            $existingAccount->update(['access_token' => $accessToken]);

            return [$existingChannel, $existingAccount, $existingPhoneNumber];
        }

        $account = WhatsAppAccount::query()->where('waba_id', $wabaId)->first();

        if ($account !== null && ($account->team_id !== $team->getKey()
            || $account->connection_method !== WhatsAppConnectionMethod::TechProvider)) {
            throw new WhatsAppConnectionException('Esta Conta do WhatsApp já está vinculada a outra configuração.');
        }

        if (WhatsAppPhoneNumber::query()->where('phone_number_id', $phoneNumberId)->exists()) {
            throw new WhatsAppConnectionException('Este número do WhatsApp já está vinculado a uma configuração.');
        }

        return DB::transaction(function () use ($team, $wabaId, $phoneNumberId, $accessToken, $account): array {
            $channel = $team->channels()->create([
                'type' => ChannelType::WhatsApp,
                'status' => ChannelStatus::Error,
                'last_error' => null,
            ]);

            $account ??= $team->whatsappAccounts()->create([
                'connection_method' => WhatsAppConnectionMethod::TechProvider,
                'waba_id' => $wabaId,
                'access_token' => $accessToken,
                'app_id' => null,
                'app_secret' => null,
                'verify_token' => null,
            ]);

            $phoneNumber = $channel->whatsappPhoneNumber()->create([
                'whatsapp_account_id' => $account->getKey(),
                'phone_number_id' => $phoneNumberId,
            ]);

            return [$channel, $account, $phoneNumber];
        });
    }

    private function subscribeToWaba(WhatsAppAccount $account): void
    {
        $response = $this->graphRequest($account->access_token)->post(
            $this->graphUrl(rawurlencode($account->waba_id).'/subscribed_apps'),
        );

        $this->throwIfMetaRejected($response);
        $account->update(['subscribed_at' => now()]);
    }

    private function registerPhoneNumber(WhatsAppPhoneNumber $phoneNumber, WhatsAppAccount $account): void
    {
        $pin = $phoneNumber->registration_pin ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $phoneNumber->update(['registration_pin' => $pin]);

        $response = $this->graphRequest($account->access_token)->post(
            $this->graphUrl(rawurlencode($phoneNumber->phone_number_id).'/register'),
            [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ],
        );

        $this->throwIfMetaRejected($response);
        $phoneNumber->update(['registered_at' => now()]);
    }

    private function syncPhoneNumberDetails(WhatsAppPhoneNumber $phoneNumber, WhatsAppAccount $account): void
    {
        $response = $this->graphRequest($account->access_token)->get(
            $this->graphUrl(rawurlencode($phoneNumber->phone_number_id)),
            ['fields' => 'display_phone_number,verified_name,quality_rating,name_status,messaging_limit_tier'],
        );

        $this->throwIfMetaRejected($response);
        $phoneNumber->update([
            'display_phone_number' => $response->json('display_phone_number'),
            'verified_name' => $response->json('verified_name'),
            'quality_rating' => $response->json('quality_rating'),
            'name_status' => $response->json('name_status'),
            'messaging_limit_tier' => $response->json('messaging_limit_tier'),
        ]);
    }

    private function throwIfMetaRejected(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new WhatsAppConnectionException($this->translatedMetaError($response));
    }

    private function translatedMetaError(Response $response): string
    {
        $error = $response->json('error');

        return $this->errorTranslator->translate(is_array($error) ? $error : null);
    }

    private function markConnectionAsFailed(Channel $channel, string $message): void
    {
        $channel->update([
            'status' => ChannelStatus::Error,
            'last_error' => $message,
        ]);
    }

    private function graphRequest(?string $accessToken = null): PendingRequest
    {
        $request = Http::acceptJson()->connectTimeout(3)->timeout(15);

        return $accessToken === null ? $request : $request->withToken($accessToken);
    }

    private function graphUrl(string $path): string
    {
        return rtrim((string) config('services.whatsapp.graph_api_url'), '/')
            .'/'.trim((string) config('services.whatsapp.graph_api_version'), '/')
            .'/'.$path;
    }
}
