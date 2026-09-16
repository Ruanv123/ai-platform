<?php

namespace App\Knowledge;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use League\HTMLToMarkdown\HtmlConverter;

class DocumentUrlExtractor
{
    public function extract(string $url): string
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
}
