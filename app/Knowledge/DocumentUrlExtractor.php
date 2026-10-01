<?php

namespace App\Knowledge;

class DocumentUrlExtractor
{
    public function __construct(private PageReader $pageReader) {}

    public function extract(string $url): string
    {
        return $this->pageReader->readMarkdown($url);
    }
}
