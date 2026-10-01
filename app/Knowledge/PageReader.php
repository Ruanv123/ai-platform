<?php

namespace App\Knowledge;

use DOMDocument;
use DOMXPath;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use League\HtmlToMarkdown\HtmlConverter;

class PageReader
{
    public function readMarkdown(string $url): string
    {
        if ($this->hasFirecrawlKey()) {
            $markdown = $this->firecrawlMarkdown($url);

            if ($markdown !== null) {
                return $markdown;
            }
        }

        return $this->readWithReadability($url);
    }

    public function mapUrls(string $url): array
    {
        if ($this->hasFirecrawlKey()) {
            $urls = $this->firecrawlMap($url);

            if ($urls !== null) {
                return $urls;
            }
        }

        return $this->mapHtmlLinks($this->fetchHtml($url), $url);
    }

    private function firecrawlMarkdown(string $url): ?string
    {
        try {
            $response = Http::withToken((string) config('services.firecrawl.key'))
                ->connectTimeout((int) config('knowledge.url.connect_timeout', 3))
                ->timeout((int) config('knowledge.url.firecrawl_timeout', 45))
                ->post($this->firecrawlEndpoint('scrape'), [
                    'url' => $url,
                    'formats' => ['markdown'],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        $markdown = is_array($data) ? ($data['markdown'] ?? null) : null;

        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_string($markdown)) {
            return null;
        }

        $markdown = trim($markdown);

        return $markdown === '' ? null : $markdown;
    }

    private function firecrawlMap(string $url): ?array
    {
        try {
            $response = Http::withToken((string) config('services.firecrawl.key'))
                ->connectTimeout((int) config('knowledge.url.connect_timeout', 3))
                ->timeout((int) config('knowledge.url.firecrawl_timeout', 45))
                ->post($this->firecrawlEndpoint('map'), ['url' => $url]);
        } catch (ConnectionException) {
            return null;
        }

        $payload = $response->json();
        $links = is_array($payload) ? ($payload['links'] ?? null) : null;

        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($links)) {
            return null;
        }

        $urls = [];

        foreach ($links as $link) {
            $linkUrl = is_string($link) ? $link : (is_array($link) ? ($link['url'] ?? null) : null);

            if (is_string($linkUrl)) {
                $linkUrl = trim($linkUrl);

                if ($this->isSameSiteWebUrl($url, $linkUrl)) {
                    $urls[] = $linkUrl;
                }
            }
        }

        $urls = array_values(array_unique(array_filter($urls, static fn (string $linkUrl): bool => $linkUrl !== '')));

        return $urls === [] ? null : $urls;
    }

    private function readWithReadability(string $url): string
    {
        $html = $this->fetchHtml($url);
        $configuration = new Configuration([
            'fixRelativeURLs' => true,
            'originalURL' => $url,
        ]);
        $readability = new Readability($configuration);

        try {
            $readability->parse($html);
        } catch (ParseException) {
            throw new UrlDocumentException('Não foi possível extrair o conteúdo principal da página.');
        }

        $content = trim($readability->getContent() ?? '');

        if ($content === '') {
            throw new UrlDocumentException('Não foi possível extrair o conteúdo principal da página.');
        }

        $title = trim((string) $readability->getTitle());
        $content = $title === ''
            ? $content
            : '<h1>'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h1>'.$content;

        $markdown = trim((new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'remove_nodes' => 'script style noscript iframe',
        ]))->convert($content));
        $markdown = preg_replace('/\n[ \t]+(?=\S)/u', "\n", $markdown) ?? $markdown;
        $markdown = trim($markdown);

        if ($markdown === '') {
            throw new UrlDocumentException('Não foi possível extrair o conteúdo principal da página.');
        }

        return $markdown;
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::withHeaders(['Accept' => 'text/html'])
                ->connectTimeout((int) config('knowledge.url.connect_timeout', 3))
                ->timeout((int) config('knowledge.url.timeout', 10))
                ->get($url);
        } catch (ConnectionException) {
            throw new UrlDocumentException('Não foi possível acessar a página informada.');
        }

        if (! $response->successful()) {
            throw new UrlDocumentException(sprintf(
                'A página retornou um erro HTTP (%d).',
                $response->status(),
            ));
        }

        $html = trim($response->body());

        if ($html === '') {
            throw new UrlDocumentException('A página não possui conteúdo para processar.');
        }

        return $html;
    }

    private function mapHtmlLinks(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;
        $previousErrorState = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorState);
        }

        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[@href]');
        $urls = [];

        if ($links !== false) {
            foreach ($links as $link) {
                $resolvedUrl = $this->resolveUrl($baseUrl, $link->getAttribute('href'));

                if ($resolvedUrl !== null && $this->isSameSiteWebUrl($baseUrl, $resolvedUrl)) {
                    $urls[] = $resolvedUrl;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        $hrefParts = parse_url($href);
        $baseParts = parse_url($baseUrl);

        if ($hrefParts === false || $baseParts === false || ! isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        if (isset($hrefParts['scheme'])) {
            return $this->isWebUrl($href) ? $href : null;
        }

        $scheme = strtolower($baseParts['scheme']);
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $origin = $scheme.'://'.$host.$port;

        if (str_starts_with($href, '//')) {
            $resolvedUrl = $scheme.':'.$href;

            return $this->isWebUrl($resolvedUrl) ? $resolvedUrl : null;
        }

        $hrefPath = $hrefParts['path'] ?? '';

        if ($hrefPath === '') {
            $path = $baseParts['path'] ?? '/';
            $query = $hrefParts['query'] ?? ($baseParts['query'] ?? null);
        } else {
            $basePath = $baseParts['path'] ?? '/';
            $path = str_starts_with($hrefPath, '/')
                ? $hrefPath
                : substr($basePath, 0, strrpos($basePath, '/') === false ? 0 : strrpos($basePath, '/') + 1).$hrefPath;
            $query = $hrefParts['query'] ?? null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $resolvedUrl = $origin.'/'.implode('/', $segments);

        if (is_string($query)) {
            $resolvedUrl .= '?'.$query;
        }

        if (isset($hrefParts['fragment'])) {
            $resolvedUrl .= '#'.$hrefParts['fragment'];
        }

        return $this->isWebUrl($resolvedUrl) ? $resolvedUrl : null;
    }

    private function isWebUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    private function isSameSiteWebUrl(string $siteUrl, string $candidateUrl): bool
    {
        if (! $this->isWebUrl($siteUrl) || ! $this->isWebUrl($candidateUrl)) {
            return false;
        }

        $siteHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
        $candidateHost = strtolower((string) parse_url($candidateUrl, PHP_URL_HOST));
        $siteHost = preg_replace('/^www\./', '', $siteHost) ?? $siteHost;
        $candidateHost = preg_replace('/^www\./', '', $candidateHost) ?? $candidateHost;

        return $siteHost === $candidateHost;
    }

    private function hasFirecrawlKey(): bool
    {
        return trim((string) config('services.firecrawl.key')) !== '';
    }

    private function firecrawlEndpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('services.firecrawl.base_url', 'https://api.firecrawl.dev/v2'), '/');

        return $baseUrl.'/'.$path;
    }
}
