<?php

namespace App\Knowledge;

class DocumentUrlExtractor
{
    public function __construct(private PageReader $pageReader) {}

    public function extract(string $url, ?int $siteReadId = null): string
    {
        return $this->pageReader->readMarkdown($url, $siteReadId);
    }
}
