<?php

namespace App\Knowledge;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileStorage
{
    public function store(UploadedFile $file, int $teamId): string
    {
        $path = $file->store('documents/'.$teamId, $this->disk());

        if ($path === false) {
            throw new RuntimeException('Não foi possível armazenar o arquivo.');
        }

        return $path;
    }

    public function originalFilename(UploadedFile $file): string
    {
        $filename = basename($file->getClientOriginalName());
        $filename = preg_replace('/[^\pL\pN ._()\-]+/u', '_', $filename);
        $extension = strtolower($file->getClientOriginalExtension());

        if (! is_string($filename) || trim($filename) === '') {
            return 'documento.'.$extension;
        }

        $suffix = $extension === '' ? '' : '.'.$extension;
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return mb_substr($baseName, 0, 255 - strlen($suffix)).$suffix;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(
            $document->file_path !== null
                && Storage::disk($this->disk())->exists($document->file_path),
            404,
        );

        return Storage::disk($this->disk())->download(
            $document->file_path,
            $document->original_filename ?? 'documento',
        );
    }

    public function contents(string $path): string
    {
        return Storage::disk($this->disk())->get($path);
    }

    private function disk(): string
    {
        return (string) config('knowledge.files_disk', 'local');
    }
}
