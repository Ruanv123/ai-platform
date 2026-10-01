<?php

namespace App\Jobs;

use App\Enums\SiteReadPageStatus;
use App\Knowledge\SiteReadProgress;
use App\Models\Document;
use App\Models\SiteReadPage;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IngestSiteReadPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $siteReadPageId, public int $documentId)
    {
        $this->onQueue('ingestion');
    }

    public function handle(Dispatcher $dispatcher, SiteReadProgress $progress): void
    {
        $page = SiteReadPage::withoutGlobalScopes()->find($this->siteReadPageId);
        $document = Document::withoutGlobalScopes()->find($this->documentId);

        if ($page === null || $document === null) {
            return;
        }

        if ($page->status !== SiteReadPageStatus::Pending) {
            return;
        }

        $dispatcher->dispatchSync(new IngestDocument($document->getKey(), $document->ingestion_token));
        $progress->completePage($page->getKey());
    }

    public function failed(?Throwable $exception): void
    {
        app(SiteReadProgress::class)->failPage(
            $this->siteReadPageId,
            'Não foi possível processar esta página. Tente novamente mais tarde.',
        );
    }
}
