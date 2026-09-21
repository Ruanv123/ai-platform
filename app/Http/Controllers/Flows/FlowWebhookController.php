<?php

namespace App\Http\Controllers\Flows;

use App\Flows\FlowEngine;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FlowWebhookController extends Controller
{
    public function handle(Request $request, string $channelId, string $event, FlowEngine $engine): JsonResponse
    {
        $channel = Channel::withoutGlobalScopes()->find($channelId);
        if ($channel === null) {
            return response()->json(['message' => 'Canal não encontrado.'], 404);
        }

        $payload = $request->json()->all();

        $flows = Flow::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('trigger_type', 'webhook')
            ->where('trigger_event', $event)
            ->where('status', 'published')
            ->get();

        if ($flows->isEmpty()) {
            return response()->json(['message' => 'Nenhum Fluxo atende este evento.'], 404);
        }

        $token = $request->header('X-Flow-Token');
        $accepted = false;
        foreach ($flows as $flow) {
            if ($flow->trigger_token_hash !== null
                && (! is_string($token) || ! hash_equals($flow->trigger_token_hash, hash('sha256', $token)))) {
                continue;
            }

            $phone = $this->phone($payload);
            $contact = $phone === null
                ? null
                : Contact::withoutGlobalScopes()->where('team_id', $channel->team_id)->where('phone', $phone)->first();

            if ($contact === null && ! $flow->allow_unknown_contact) {
                return response()->json(['message' => 'O telefone do Contato não é conhecido.'], 422);
            }

            $engine->handleWebhook($flow, $channel, $payload, $contact);
            $accepted = true;
        }

        return $accepted
            ? response()->json(['accepted' => true])
            : response()->json(['message' => 'Token do Gatilho inválido.'], 401);
    }

    /** @param array<string, mixed> $payload */
    private function phone(array $payload): ?string
    {
        $value = data_get($payload, 'phone')
            ?? data_get($payload, 'contact_phone')
            ?? data_get($payload, 'contact.phone')
            ?? data_get($payload, 'from');
        if (! is_scalar($value) || blank($value)) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', (string) $value);

        return is_string($phone) && filled($phone) ? Str::substr($phone, 0, 32) : null;
    }
}
