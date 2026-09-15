<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle the public WhatsApp webhook handshake and event callbacks.
     */
    public function handle(Request $request): Response
    {
        return $request->isMethod('GET')
            ? $this->verify($request)
            : $this->receive($request);
    }

    /**
     * Complete Meta's webhook verification handshake.
     */
    private function verify(Request $request): Response
    {
        $mode = $this->queryParameter($request, 'hub.mode');
        $verifyToken = $this->queryParameter($request, 'hub.verify_token');
        $challenge = $this->queryParameter($request, 'hub.challenge');

        if ($mode !== 'subscribe' || blank($verifyToken) || blank($challenge)) {
            return response('Forbidden', 403);
        }

        $whatsappNumber = WhatsAppNumber::withoutGlobalScopes()
            ->where('verify_token', $verifyToken)
            ->first();

        if ($whatsappNumber === null) {
            return response('Forbidden', 403);
        }

        $whatsappNumber->update([
            'webhook_verified_at' => now(),
        ]);

        return response($challenge, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Validate a signed event without processing it during this phase.
     */
    private function receive(Request $request): Response
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        $phoneNumberId = is_array($payload)
            ? data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id')
            : null;
        $signature = $request->header('X-Hub-Signature-256');

        if (! is_string($phoneNumberId) || blank($phoneNumberId) || blank($signature)) {
            return response('Forbidden', 403);
        }

        $whatsappNumber = WhatsAppNumber::withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($whatsappNumber === null) {
            return response('Forbidden', 403);
        }

        $expectedSignature = 'sha256='.hash_hmac(
            'sha256',
            $rawBody,
            $whatsappNumber->app_secret,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return response('Forbidden', 403);
        }

        return response('EVENT_RECEIVED');
    }

    /**
     * Read Meta's dotted query parameters across PHP's normalized query keys.
     */
    private function queryParameter(Request $request, string $name): ?string
    {
        $value = $request->query($name) ?? $request->query(str_replace('.', '_', $name));

        return is_scalar($value) ? (string) $value : null;
    }
}
