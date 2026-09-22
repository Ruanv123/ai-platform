<?php

namespace App\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class WhatsAppTemplateSynchronizer
{
    public function __construct(private readonly WhatsAppTemplateService $service) {}

    /**
     * Synchronize one WABA and keep removed Meta templates as tombstones.
     *
     * @return array{imported: int, removed: int, templates: int}
     */
    public function sync(WhatsAppAccount $account): array
    {
        $remoteTemplates = [];
        $url = $this->templatesUrl($account);
        $pages = 0;

        do {
            $response = $this->request($account)->get($url, ['limit' => 100]);
            $response->throw();
            $body = $response->json();
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            foreach ($data as $template) {
                if (is_array($template)) {
                    $remoteTemplates[] = $template;
                }
            }
            $url = is_string(data_get($body, 'paging.next')) ? data_get($body, 'paging.next') : null;
            $pages++;
        } while ($url !== null && $pages < 20);

        $imported = 0;
        $remoteIds = [];
        foreach ($remoteTemplates as $remote) {
            $template = $this->service->upsertFromMeta($account, $remote);
            $remoteId = $template->meta_template_id;
            if ($remoteId !== null) {
                $remoteIds[] = $remoteId;
            }
            $imported++;
        }

        $removed = 0;
        $localTemplates = WhatsAppTemplate::query()
            ->where('whatsapp_account_id', $account->getKey())
            ->whereNotNull('meta_template_id')
            ->get();
        foreach ($localTemplates as $template) {
            if (in_array($template->meta_template_id, $remoteIds, true)) {
                continue;
            }
            $this->service->markDeleted($template);
            $removed++;
        }

        return [
            'imported' => $imported,
            'removed' => $removed,
            'templates' => $imported + $removed,
        ];
    }

    private function request(WhatsAppAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)->connectTimeout(3)->timeout(15);
    }

    private function templatesUrl(WhatsAppAccount $account): string
    {
        return rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/').'/'.trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/').'/'.rawurlencode($account->waba_id).'/message_templates';
    }
}
