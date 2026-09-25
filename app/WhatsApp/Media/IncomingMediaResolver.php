<?php

namespace App\WhatsApp\Media;

use App\Knowledge\DocumentFileExtractor;
use Laravel\Ai\Files\Image as AiImage;
use Laravel\Ai\Image;
use Laravel\Ai\Transcription;
use RuntimeException;

use function Laravel\Ai\agent;

final class IncomingMediaResolver
{
    public function __construct(private readonly DocumentFileExtractor $fileExtractor) {}

    /**
     * Resolve a downloaded incoming media file into text.
     *
     * @param  array{contents: string, mime_type: string|null, filename: string}  $media
     */
    public function resolve(string $type, array $media): string
    {
        if ($media['contents'] === '') {
            throw new RuntimeException('A mídia recebida está vazia.');
        }

        return match ($type) {
            'audio' => $this->transcribeAudio($media),
            'image' => $this->describeImage($media),
            'document' => $this->extractDocument($media),
            default => throw new RuntimeException('Este tipo de mídia não pode ser processado.'),
        };
    }

    /**
     * Preserve the incoming caption while adding the resolved media text.
     */
    public function withCaption(?string $caption, string $resolvedContent): string
    {
        return collect([$caption, trim($resolvedContent)])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode("\n\n");
    }

    /**
     * Return a user-visible result when media processing fails.
     */
    public function failure(?string $caption): string
    {
        return $this->withCaption(
            $caption,
            'Não foi possível processar esta mídia. O atendimento continuará com as demais mensagens.',
        );
    }

    /**
     * @param  array{contents: string, mime_type: string|null, filename: string}  $media
     */
    private function transcribeAudio(array $media): string
    {
        return $this->withTemporaryFile($media['contents'], 'whatsapp-audio-', function (string $path) use ($media): string {
            $transcription = Transcription::fromPath($path, $media['mime_type'])
                ->language('pt')
                ->generate(config('ai.default_for_transcription', 'openai'));

            $text = trim((string) $transcription);

            if ($text === '') {
                throw new RuntimeException('A transcrição do áudio não retornou texto.');
            }

            return $text;
        });
    }

    /**
     * @param  array{contents: string, mime_type: string|null, filename: string}  $media
     */
    private function describeImage(array $media): string
    {
        return $this->withTemporaryFile($media['contents'], 'whatsapp-image-', function (string $path) use ($media): string {
            $prompt = 'Descreva em português, de forma objetiva e detalhada, o que aparece nesta imagem. Retorne somente a descrição que será usada para entender a mensagem do contato.';
            $provider = (string) config('ai.default_for_images', 'openai');
            $image = AiImage::fromPath($path, $media['mime_type']);

            if (Image::isFaked()) {
                $response = Image::of($prompt)
                    ->attachments([$image])
                    ->generate($provider);
                $description = trim($response->firstImage()->image);
            } else {
                $response = agent(
                    instructions: 'Você descreve imagens recebidas em mensagens de atendimento. Responda sempre em português e apenas com a descrição visual solicitada.',
                )->prompt($prompt, [$image], provider: $provider);
                $description = trim($response->text);
            }

            if ($description === '') {
                throw new RuntimeException('A descrição da imagem não retornou texto.');
            }

            return $description;
        });
    }

    /**
     * @param  array{contents: string, mime_type: string|null, filename: string}  $media
     */
    private function extractDocument(array $media): string
    {
        return $this->fileExtractor->extractContents($media['contents'], $media['filename']);
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withTemporaryFile(string $contents, string $prefix, callable $callback): mixed
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new RuntimeException('Não foi possível preparar a mídia para leitura.');
        }

        try {
            if (file_put_contents($path, $contents) === false) {
                throw new RuntimeException('Não foi possível preparar a mídia para leitura.');
            }

            return $callback($path);
        } finally {
            @unlink($path);
        }
    }
}
