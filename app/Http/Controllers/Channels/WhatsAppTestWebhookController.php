<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppTestWebhook;
use App\Models\WhatsAppTestConnection;
use App\WhatsApp\Testes\EvolutionGoWebhookFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppTestWebhookController extends Controller
{
    public function __construct(
        private readonly EvolutionGoWebhookFilter $filter,
    ) {}

    public function handle(Request $request): Response
    {
        $body = $request->json()->all();
        $event = is_scalar($body['event'] ?? null) ? Str::upper((string) $body['event']) : 'UNKNOWN';
        $instanceName = $body['instanceName'] ?? null;

        Log::debug('[WHATSAPP-TEST] webhook.received', [
            'event' => $event,
            'instance_name' => is_string($instanceName) ? $instanceName : null,
        ]);

        if (! is_string($instanceName) || blank($instanceName)) {
            return response('Forbidden', 403);
        }

        $connection = WhatsAppTestConnection::query()
            ->where('instance_name', $instanceName)
            ->with('channel')
            ->first();

        if ($connection === null || $connection->channel->type !== ChannelType::WhatsAppTest) {
            Log::warning('[WHATSAPP-TEST] webhook.rejected', [
                'event' => $event,
                'instance_name' => $instanceName,
                'reason' => 'instance_not_found',
            ]);

            return response('Forbidden', 403);
        }

        if ($this->filter->shouldProcess($body)) {
            ProcessWhatsAppTestWebhook::dispatch($connection->channel_id, $body);
        }

        return response('EVENT_RECEIVED');
    }
}
