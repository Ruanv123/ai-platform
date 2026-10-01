<?php

namespace App\Jobs;

use App\Ai\Agents\SelectSitePages;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadStatus;
use App\Knowledge\PageReader;
use App\Knowledge\SiteReadProgress;
use App\Models\Collection;
use App\Models\Document;
use App\Models\SiteRead;
use App\Models\SiteReadPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ReadSite implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $siteReadId)
    {
        $this->onQueue('ingestion');
    }

    public function handle(PageReader $pageReader): void
    {
        $siteRead = SiteRead::withoutGlobalScopes()->find($this->siteReadId);

        if ($siteRead === null || $siteRead->status !== SiteReadStatus::InProgress) {
            return;
        }

        $maximumPages = max(1, (int) config('knowledge.site_read.maximum_pages', 25));
        $homeUrl = $this->homeUrl($siteRead->website);
        $mappedUrls = $pageReader->mapUrls($siteRead->website);
        $candidateUrls = array_values(array_unique([$homeUrl, ...$mappedUrls]));
        $additionalUrls = array_values(array_filter($candidateUrls, fn (string $url): bool => $url !== $homeUrl));
        $prompt = sprintf(
            "Escolha até %d URLs além da página inicial para uma leitura institucional. URLs disponíveis:\n%s",
            max(0, $maximumPages - 1),
            json_encode($additionalUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $selection = (new SelectSitePages($maximumPages))->prompt($prompt);
        $faqUrl = $selection['faq_url'] ?? null;
        $selectedUrls = $this->selectedUrls($selection['urls'] ?? [], $additionalUrls, $homeUrl, $maximumPages, $faqUrl);
        $faqUrl = is_string($faqUrl) && in_array($faqUrl, $selectedUrls, true) ? $faqUrl : null;

        $pages = DB::transaction(function () use ($siteRead, $candidateUrls, $selectedUrls, $faqUrl): array {
            $lockedSiteRead = SiteRead::withoutGlobalScopes()->lockForUpdate()->findOrFail($siteRead->getKey());
            $team = $lockedSiteRead->team()->lockForUpdate()->firstOrFail();
            $collection = Collection::withoutGlobalScopes()
                ->where('team_id', $team->getKey())
                ->where('is_site_collection', true)
                ->first();

            if ($collection === null) {
                $collectionName = 'Coleção do site';
                $suffix = 2;

                while (Collection::withoutGlobalScopes()
                    ->where('team_id', $team->getKey())
                    ->where('name', $collectionName)
                    ->exists()) {
                    $collectionName = 'Coleção do site ('.$suffix.')';
                    $suffix++;
                }

                $collection = Collection::withoutGlobalScopes()->create([
                    'team_id' => $team->getKey(),
                    'name' => $collectionName,
                    'description' => 'Páginas lidas do site da Empresa.',
                    'is_default' => false,
                    'is_site_collection' => true,
                ]);
            }

            $lockedSiteRead->update(['found_pages_count' => count($candidateUrls)]);
            $sitePages = [];

            foreach ($selectedUrls as $url) {
                $page = SiteReadPage::withoutGlobalScopes()->firstOrCreate([
                    'site_read_id' => $lockedSiteRead->getKey(),
                    'url' => $url,
                ], [
                    'team_id' => $team->getKey(),
                    'title' => $this->titleForUrl($url),
                    'status' => SiteReadPageStatus::Pending,
                ]);

                $document = Document::withoutGlobalScopes()->firstOrCreate([
                    'site_read_page_id' => $page->getKey(),
                ], [
                    'team_id' => $team->getKey(),
                    'collection_id' => $collection->getKey(),
                    'type' => DocumentType::Url,
                    'status' => DocumentStatus::Pending,
                    'title' => $page->title,
                    'content' => '',
                    'url' => $url,
                    'size_bytes' => 0,
                    'ingestion_token' => (string) Str::uuid(),
                ]);

                $sitePages[] = [$page, $document, $url === $faqUrl];
            }

            return $sitePages;
        });

        foreach ($pages as [$page, $document, $isFaqPage]) {
            IngestSiteReadPage::dispatch($page->getKey(), $document->getKey(), $isFaqPage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(SiteReadProgress::class)->failRead(
            $this->siteReadId,
            'Não foi possível preparar a leitura do site. Tente novamente em alguns minutos.',
        );
    }

    private function homeUrl(string $website): string
    {
        $parts = parse_url($website);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.'/';
    }

    private function selectedUrls(mixed $urls, array $availableUrls, string $homeUrl, int $maximumPages, mixed $faqUrl): array
    {
        if (! is_array($urls)) {
            $urls = [];
        }

        $selected = [$homeUrl];
        $faqUrl = is_string($faqUrl) && ($faqUrl === $homeUrl || in_array($faqUrl, $availableUrls, true))
            ? $faqUrl
            : null;

        if ($faqUrl !== null && $faqUrl !== $homeUrl && count($selected) < $maximumPages) {
            $selected[] = $faqUrl;
        }

        foreach ($urls as $url) {
            if (! is_string($url) || ! in_array($url, $availableUrls, true) || in_array($url, $selected, true)) {
                continue;
            }

            if (count($selected) >= $maximumPages) {
                break;
            }

            $selected[] = $url;
        }

        return $selected;
    }

    private function titleForUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return 'Página inicial';
        }

        $slug = basename($path);
        $title = Str::of($slug)->replace(['-', '_'], ' ')->squish()->title()->toString();

        return $title === '' ? 'Página do site' : $title;
    }
}
