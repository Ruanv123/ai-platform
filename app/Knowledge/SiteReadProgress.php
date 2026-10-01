<?php

namespace App\Knowledge;

use App\Enums\DocumentStatus;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadStatus;
use App\Models\Document;
use App\Models\SiteRead;
use App\Models\SiteReadPage;
use Illuminate\Support\Facades\DB;

class SiteReadProgress
{
    public function completePage(int $siteReadPageId): void
    {
        DB::transaction(function () use ($siteReadPageId): void {
            $page = SiteReadPage::withoutGlobalScopes()->lockForUpdate()->find($siteReadPageId);

            if ($page === null || $page->status !== SiteReadPageStatus::Pending) {
                return;
            }

            $document = Document::withoutGlobalScopes()
                ->where('site_read_page_id', $page->getKey())
                ->first();

            if ($document === null || $document->status !== DocumentStatus::Ready) {
                $page->update([
                    'status' => SiteReadPageStatus::Failed,
                    'error_message' => $document?->error_message ?: 'Não foi possível processar esta página.',
                    'completed_at' => now(),
                ]);
            } else {
                $page->update([
                    'status' => SiteReadPageStatus::Read,
                    'error_message' => null,
                    'completed_at' => now(),
                ]);
            }

            $this->finishReadWhenReady($page->site_read_id);
        });
    }

    public function failPage(int $siteReadPageId, string $message): void
    {
        DB::transaction(function () use ($siteReadPageId, $message): void {
            $page = SiteReadPage::withoutGlobalScopes()->lockForUpdate()->find($siteReadPageId);

            if ($page === null || $page->status !== SiteReadPageStatus::Pending) {
                return;
            }

            Document::withoutGlobalScopes()
                ->where('site_read_page_id', $page->getKey())
                ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])
                ->update([
                    'status' => DocumentStatus::Failed,
                    'error_message' => $message,
                ]);

            $page->update([
                'status' => SiteReadPageStatus::Failed,
                'error_message' => $message,
                'completed_at' => now(),
            ]);

            $this->finishReadWhenReady($page->site_read_id);
        });
    }

    public function failRead(int $siteReadId, string $message): void
    {
        DB::transaction(function () use ($siteReadId, $message): void {
            $siteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->find($siteReadId);

            if ($siteRead === null || $siteRead->status !== SiteReadStatus::InProgress) {
                return;
            }

            $pendingPageIds = SiteReadPage::withoutGlobalScopes()
                ->where('site_read_id', $siteRead->getKey())
                ->where('status', SiteReadPageStatus::Pending)
                ->pluck('id');

            if ($pendingPageIds->isNotEmpty()) {
                Document::withoutGlobalScopes()
                    ->whereIn('site_read_page_id', $pendingPageIds)
                    ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Processing])
                    ->update([
                        'status' => DocumentStatus::Failed,
                        'error_message' => $message,
                    ]);

                SiteReadPage::withoutGlobalScopes()
                    ->whereIn('id', $pendingPageIds)
                    ->update([
                        'status' => SiteReadPageStatus::Failed,
                        'error_message' => $message,
                        'completed_at' => now(),
                    ]);
            }

            $this->finishReadWhenReady($siteRead->getKey(), $message);
        });
    }

    private function finishReadWhenReady(int $siteReadId, ?string $fallbackMessage = null): void
    {
        $siteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->find($siteReadId);

        if ($siteRead === null || $siteRead->status !== SiteReadStatus::InProgress) {
            return;
        }

        $pages = SiteReadPage::withoutGlobalScopes()->where('site_read_id', $siteReadId);

        if ((clone $pages)->where('status', SiteReadPageStatus::Pending)->exists()) {
            return;
        }

        $readPagesCount = (clone $pages)->where('status', SiteReadPageStatus::Read)->count();
        $failedPagesCount = (clone $pages)->where('status', SiteReadPageStatus::Failed)->count();
        $firstPageError = (clone $pages)->where('status', SiteReadPageStatus::Failed)->value('error_message');

        $siteRead->update([
            'status' => $readPagesCount > 0 ? SiteReadStatus::Completed : SiteReadStatus::Failed,
            'error_message' => $readPagesCount > 0
                ? null
                : ($firstPageError ?: $fallbackMessage ?: 'Não foi possível ler nenhuma página do site. Confira se o site está acessível e tente novamente.'),
            'completed_at' => now(),
        ]);
    }
}
