<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class InboxMediaController extends Controller
{
    public function show(Request $request, Contact $contact, Channel $channel, Message $message): BinaryFileResponse
    {
        $team = $request->user()->company();

        abort_unless($team !== null
            && $contact->team_id === $team->getKey()
            && $channel->team_id === $team->getKey()
            && $message->team_id === $team->getKey()
            && $message->contact_id === $contact->getKey()
            && $message->channel_id === $channel->getKey(), 404);

        abort_unless($message->media_available === true && filled($message->media_path), 404);

        $disk = Storage::disk('whatsapp_media');
        abort_unless($disk->exists($message->media_path), 404);

        $filename = $message->media_filename ?: 'whatsapp-media.bin';
        $mimeType = $message->media_mime_type ?: 'application/octet-stream';
        $inlineTypes = [
            'application/pdf',
            'audio/aac',
            'audio/amr',
            'audio/mp4',
            'audio/mpeg',
            'audio/ogg',
            'audio/wav',
            'audio/webm',
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/3gpp',
            'video/mp4',
            'video/quicktime',
            'video/webm',
            'text/plain',
        ];
        $disposition = in_array($mimeType, $inlineTypes, true)
            ? HeaderUtils::DISPOSITION_INLINE
            : HeaderUtils::DISPOSITION_ATTACHMENT;
        $response = response()->file($disk->path($message->media_path), [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition(
            $disposition,
            $filename,
            Str::ascii($filename) ?: 'whatsapp-media.bin',
        );

        return $response;
    }
}
