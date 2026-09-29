<?php

namespace App\Http\Controllers\Integrations;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCrmWebhook;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RdStationCrmWebhookController extends Controller
{
    public function handle(Request $request, CrmProviderAdapter $adapter): Response
    {
        $headers = $request->headers->all();
        $body = $request->getContent();

        if (! $adapter->authenticateWebhook($headers, $body)) {
            return response()->noContent(401);
        }

        $integrationId = $adapter->identifyWebhook($headers);
        if ($integrationId === null) {
            return response()->noContent(200);
        }

        $integration = Integration::withoutGlobalScopes()
            ->withTrashed()
            ->whereKey($integrationId)
            ->where('type', 'crm')
            ->where('provider', $adapter->provider())
            ->first();
        if ($integration === null || $integration->trashed() || $integration->status !== 'connected') {
            return response()->noContent(200);
        }

        $notice = $adapter->translateWebhook($request->json()->all());
        if ($notice === null) {
            return response()->noContent(200);
        }

        ProcessCrmWebhook::dispatch(
            (string) $integration->getKey(),
            $integration->team_id,
            $notice['resource'],
            $notice['external_id'],
            $notice['deleted'],
        );

        return response()->noContent(200);
    }
}
