<?php

namespace App\WhatsApp\Media;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class IncomingMediaStorage
{
    /**
     * Store a provider attachment in the private WhatsApp media disk.
     *
     * @param  array{contents: string, mime_type: string|null, filename: string}  $media
     */
    public function store(Message $message, array $media): void
    {
        $filename = $this->safeFilename($media['filename']);
        $extension = $this->safeExtension($filename);
        $path = 'teams/'.$message->team_id.'/messages/'.$message->getKey().'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('whatsapp_media');

        if (! $disk->put($path, $media['contents'], ['visibility' => 'private'])) {
            throw new RuntimeException('Não foi possível armazenar a mídia recebida.');
        }

        $message->forceFill([
            'media_path' => $path,
            'media_filename' => $filename,
            'media_mime_type' => $this->safeMimeType($media['mime_type']),
            'media_size' => strlen($media['contents']),
            'media_available' => true,
        ])->save();
    }

    public function markUnavailable(Message $message): void
    {
        $message->forceFill(['media_available' => false])->save();
    }

    public function delete(Message $message): void
    {
        if (! is_string($message->media_path) || $message->media_path === '') {
            return;
        }

        Storage::disk('whatsapp_media')->delete($message->media_path);
    }

    private function safeFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', trim($filename)));
        $safeFilename = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $basename) ?? '';
        $safeFilename = trim($safeFilename);

        return $safeFilename === '' ? 'whatsapp-media.bin' : mb_substr($safeFilename, 0, 255);
    }

    private function safeExtension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return preg_match('/\\A[a-z0-9]{1,12}\\z/', $extension) === 1 ? $extension : 'bin';
    }

    private function safeMimeType(?string $mimeType): ?string
    {
        if ($mimeType === null) {
            return null;
        }

        $value = trim(explode(';', $mimeType, 2)[0]);

        return preg_match('~\\A[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+\\z~i', $value) === 1
            ? $value
            : null;
    }
}
