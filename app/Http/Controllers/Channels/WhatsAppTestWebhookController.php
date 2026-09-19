<?php

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppTestWebhook;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppTestWebhookController extends Controller
{
    /**
     * Receive an unsigned Evolution Go webhook for the configured instance.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->json()->all();

        $instanceName = $payload['instance'] ?? null;

        if (! is_string($instanceName) || blank($instanceName)) {
            return response('Forbidden', 403);
        }

        $connection = WhatsAppTestConnection::query()
            ->where('instance_name', $instanceName)
            ->with('channel')
            ->first();

        if ($connection === null || $connection->channel->type !== ChannelType::WhatsAppTest) {
            return response('Forbidden', 403);
        }

        ProcessWhatsAppTestWebhook::dispatch($connection->channel_id, $payload);

        return response('EVENT_RECEIVED');
    }
}
