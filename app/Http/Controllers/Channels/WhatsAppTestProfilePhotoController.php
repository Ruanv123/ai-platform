<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WhatsAppTestProfilePhotoController extends Controller
{
    public function show(string $photoToken, string $extension): BinaryFileResponse
    {
        abort_unless(preg_match('/\A[A-Za-z0-9]{64}\z/', $photoToken) === 1, 404);

        $contentType = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => abort(404),
        };
        $path = 'whatsapp-profile-photos/'.$photoToken.'.'.$extension;
        $disk = Storage::disk('whatsapp_media');

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
