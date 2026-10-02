<?php

namespace App\Knowledge;

use App\Enums\DocumentStatus;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadStatus;
use App\Jobs\ExtractSiteReadProposal;
use App\Models\Document;
use App\Models\SiteRead;
use App\Models\SiteReadPage;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SiteReadProgress
{
    public function __construct(private SiteReadProposalService $proposalService) {}

    public function completePage(int $siteReadPageId): void
    {
        $siteReadId = DB::transaction(function () use ($siteReadPageId): ?int {
            $page = SiteReadPage::withoutGlobalScopes()->lockForUpdate()->find($siteReadPageId);

            if ($page === null || $page->status !== SiteReadPageStatus::Pending) {
                return null;
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

            return $this->finishReadWhenReady($page->site_read_id)
                ? $page->site_read_id
                : null;
        });

        if ($siteReadId !== null) {
            ExtractSiteReadProposal::dispatch($siteReadId);
        }
    }

    public function failPage(int $siteReadPageId, string $message): void
    {
        $siteReadId = DB::transaction(function () use ($siteReadPageId, $message): ?int {
            $page = SiteReadPage::withoutGlobalScopes()->lockForUpdate()->find($siteReadPageId);

            if ($page === null || $page->status !== SiteReadPageStatus::Pending) {
                return null;
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

            return $this->finishReadWhenReady($page->site_read_id)
                ? $page->site_read_id
                : null;
        });

        if ($siteReadId !== null) {
            ExtractSiteReadProposal::dispatch($siteReadId);
        }
    }

    public function failRead(int $siteReadId, string $message): void
    {
        $completedSiteReadId = DB::transaction(function () use ($siteReadId, $message): ?int {
            $siteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->find($siteReadId);

            if ($siteRead === null || $siteRead->status !== SiteReadStatus::InProgress) {
                return null;
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

            return $this->finishReadWhenReady($siteRead->getKey(), $message)
                ? $siteRead->getKey()
                : null;
        });

        if ($completedSiteReadId !== null) {
            ExtractSiteReadProposal::dispatch($completedSiteReadId);
        }
    }

    private function finishReadWhenReady(int $siteReadId, ?string $fallbackMessage = null): bool
    {
        $siteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->find($siteReadId);

        if ($siteRead === null || $siteRead->status !== SiteReadStatus::InProgress) {
            return false;
        }

        $pages = SiteReadPage::withoutGlobalScopes()->where('site_read_id', $siteReadId);

        if ((clone $pages)->where('status', SiteReadPageStatus::Pending)->exists()) {
            return false;
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

        if ($readPagesCount > 0) {
            $team = Team::withoutGlobalScopes()->lockForUpdate()->find($siteRead->team_id);
            $latestSiteReadId = SiteRead::withoutGlobalScopes()
                ->where('team_id', $siteRead->team_id)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->value('id');

            if ($team !== null && (int) $latestSiteReadId === (int) $siteRead->getKey()) {
                $this->proposalService->replacePendingProposals($team->getKey());
            }
        }

        return $readPagesCount > 0;
    }
}
