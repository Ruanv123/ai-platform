<?php

namespace App\Knowledge;

use App\Enums\DocumentType;
use App\Models\Document;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Spatie\PdfToText\Pdf;
use ZipArchive;

class DocumentFileExtractor
{
    public function __construct(private readonly DocumentFileStorage $storage) {}

    public function extract(Document $document): string
    {
        if ($document->type !== DocumentType::File || $document->file_path === null) {
            throw new RuntimeException('Este tipo de documento não possui um arquivo.');
        }

        return $this->extractContents(
            $this->storage->contents($document->file_path),
            $document->original_filename ?? $document->file_path,
        );
    }

    /**
     * Extract text from file contents using the same formats as knowledge documents.
     */
    public function extractContents(string $contents, string $filename): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'knowledge-document-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Não foi possível preparar o arquivo para leitura.');
        }

        try {
            if (file_put_contents($temporaryPath, $contents) === false) {
                throw new RuntimeException('Não foi possível preparar o arquivo para leitura.');
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            return match ($extension) {
                'pdf' => $this->extractPdf($temporaryPath),
                'docx' => $this->extractDocx($temporaryPath),
                'txt' => $this->extractTxt($contents),
                default => throw new RuntimeException('Este tipo de arquivo não está disponível.'),
            };
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function extractPdf(string $path): string
    {
        $binary = config('knowledge.pdf_to_text_binary');
        $text = Pdf::getText($path, is_string($binary) && $binary !== '' ? $binary : null);

        if (trim($text) === '') {
            throw new RuntimeException('PDF sem texto extraível');
        }

        return trim($text);
    }

    private function extractDocx(string $path): string
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('Não foi possível ler o arquivo DOCX.');
        }

        $xml = $archive->getFromName('word/document.xml');
        $archive->close();

        if ($xml === false) {
            throw new RuntimeException('Não foi possível ler o conteúdo do arquivo DOCX.');
        }

        $document = new DOMDocument;

        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('Não foi possível ler o conteúdo do arquivo DOCX.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $lines = [];

        foreach ($xpath->query('/w:document/w:body/*') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if ($element->localName === 'p') {
                $paragraph = $this->paragraphText($element, $xpath);

                if ($paragraph !== '') {
                    $lines[] = $this->formatHeading($element, $xpath, $paragraph);
                }

                continue;
            }

            if ($element->localName === 'tbl') {
                foreach ($xpath->query('./w:tr', $element) ?: [] as $row) {
                    if (! $row instanceof DOMElement) {
                        continue;
                    }

                    $cells = [];

                    foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                        if (! $cell instanceof DOMElement) {
                            continue;
                        }

                        $cellText = trim($this->textContent($cell, $xpath));

                        if ($cellText !== '') {
                            $cells[] = $cellText;
                        }
                    }

                    if ($cells !== []) {
                        $lines[] = implode(' | ', $cells);
                    }
                }
            }
        }

        $text = trim(implode("\n\n", $lines));

        if ($text === '') {
            throw new RuntimeException('O documento não contém texto para processar.');
        }

        return $text;
    }

    private function extractTxt(string $contents): string
    {
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        if ($encoding === false) {
            $encoding = 'Windows-1252';
        }

        $normalized = mb_convert_encoding($contents, 'UTF-8', $encoding);

        if (! is_string($normalized)) {
            throw new RuntimeException('Não foi possível normalizar o texto do arquivo.');
        }

        $text = trim($normalized);

        if ($text === '') {
            throw new RuntimeException('O documento não contém texto para processar.');
        }

        return $text;
    }

    private function paragraphText(DOMElement $paragraph, DOMXPath $xpath): string
    {
        return trim($this->textContent($paragraph, $xpath));
    }

    private function textContent(DOMElement $element, DOMXPath $xpath): string
    {
        $text = '';

        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $element) ?: [] as $node) {
            $text .= match ($node->localName) {
                'tab' => "\t",
                'br', 'cr' => "\n",
                default => $node->nodeValue ?? '',
            };
        }

        return $text;
    }

    private function formatHeading(DOMElement $paragraph, DOMXPath $xpath, string $text): string
    {
        $style = $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraph);

        if (preg_match('/^heading([1-6])$/i', $style, $matches) === 1) {
            return str_repeat('#', (int) $matches[1]).' '.$text;
        }

        if (strtolower($style) === 'title') {
            return '# '.$text;
        }

        return $text;
    }
}
