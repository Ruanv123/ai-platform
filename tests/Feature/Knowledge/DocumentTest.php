<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Jobs\IngestDocument;
use App\Knowledge\DocumentFileExtractor;
use App\Knowledge\MarkdownChunker;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

it('creates a text document, ingests it and reports its storage usage', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $content = "# Horário\n\nAtendemos de segunda a sexta.";

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'title' => 'Horário de atendimento',
        'content' => $content,
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Documento criado com sucesso.',
    ]);
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Text)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->size_bytes)->toBe(41)
        ->and($document->error_message)->toBeNull();

    $this->assertDatabaseCount('chunks', 1);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('Atendemos de segunda a sexta.'));
});

it('reprocesses edited text and replaces the previous chunks', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'title' => 'Política antiga',
        'content' => 'A política antiga não deve aparecer.',
        'status' => DocumentStatus::Ready,
        'size_bytes' => 38,
    ]);
    Chunk::factory()->for($document)->create([
        'content' => 'A política antiga não deve aparecer.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'title' => 'Política atualizada',
        'content' => 'A política atualizada deve aparecer.',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->title)->toBe('Política atualizada')
        ->and($document->size_bytes)->toBe(37);

    $this->assertDatabaseMissing('chunks', ['content' => 'A política antiga não deve aparecer.']);
    $this->assertDatabaseHas('chunks', ['content' => 'A política atualizada deve aparecer.']);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('A política atualizada deve aparecer.'));
});

it('shows documents, storage usage and polling while ingestion is pending', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->pending()->create([
        'content' => 'Aguardando processamento.',
        'size_bytes' => 25,
    ]);

    $response = $this->actingAs($user)->get(route('documents.index', $collection));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('knowledge/documents')
        ->where('collection.id', $collection->id)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.status', 'pending')
        ->where('storage.usedBytes', 25)
        ->where('polling', true),
    );
});

it('blocks a document that would exceed the company storage quota', function () {
    config(['knowledge.storage_quota_bytes' => 10]);
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'title' => 'Grande demais',
        'content' => 'Este conteúdo excede a cota.',
    ]);

    $response->assertSessionHasErrors([
        'content' => 'O conteúdo ultrapassa a cota de armazenamento de 500 MB da Empresa.',
    ]);
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

it('removes document chunks and releases its storage usage when deleted', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'content' => 'Conteúdo removível.',
        'size_bytes' => 21,
        'status' => DocumentStatus::Ready,
    ]);
    Chunk::factory()->for($document)->create([
        'content' => 'Conteúdo removível.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->delete(route('documents.destroy', $document));

    $response->assertRedirect(route('documents.index', $collection));
    $this->assertModelMissing($document);
    $this->assertDatabaseCount('chunks', 0);
});

it('moves a document and its existing chunks to another collection', function () {
    $user = User::factory()->withTeam()->create();
    $source = Collection::factory()->for($user->company())->create(['name' => 'Origem']);
    $target = Collection::factory()->for($user->company())->create(['name' => 'Destino']);
    $document = Document::factory()->for($source)->create([
        'status' => DocumentStatus::Ready,
    ]);
    $chunk = Chunk::factory()->for($document)->create([
        'content' => $document->content,
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->patch(route('documents.move', $document), [
        'collection_id' => $target->id,
    ]);

    $response->assertRedirect(route('documents.index', $target));
    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'collection_id' => $target->id,
    ]);
    $this->assertDatabaseHas('chunks', [
        'id' => $chunk->id,
        'collection_id' => $target->id,
    ]);
});

it('marks an ingestion as failed and preserves the previous chunks', function () {
    Embeddings::fake(function (): never {
        throw new RuntimeException('Provider unavailable');
    });

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Pending,
        'ingestion_token' => '00000000-0000-0000-0000-000000000001',
    ]);
    $chunk = Chunk::factory()->for($document)->create([
        'content' => 'Versão anterior preservada.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);
    $job = new IngestDocument($document->id, '00000000-0000-0000-0000-000000000001');

    try {
        $job->handle(app(MarkdownChunker::class), app(DocumentFileExtractor::class));
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('Não foi possível processar o documento. Tente novamente.');
    $this->assertModelExists($chunk);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains($document->content));
});

it('reprocesses a failed document through HTTP', function () {
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Failed,
        'error_message' => 'Falha anterior.',
    ]);

    $response = $this->actingAs($user)->post(route('documents.reprocess', $document));

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->error_message)->toBeNull();
    Queue::assertPushed(IngestDocument::class, fn (IngestDocument $job): bool => $job->documentId === $document->id && $job->queue === 'ingestion'
    );
});

it('deletes documents and chunks when their collection is deleted', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create(['status' => DocumentStatus::Ready]);
    $chunk = Chunk::factory()->for($document)->create();

    $response = $this->actingAs($user)->delete(route('collections.destroy', $collection));

    $response->assertRedirect(route('collections.index'));
    $this->assertModelMissing($collection);
    $this->assertModelMissing($document);
    $this->assertModelMissing($chunk);
});

it('returns not found for another company document', function () {
    $user = User::factory()->withTeam()->create();
    $document = Document::factory()->create();

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'title' => 'Tentativa',
        'content' => 'Não pode alterar.',
    ]);

    $response->assertNotFound();
});

it('forbids members from changing documents', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $member->teams()->attach($owner->company(), ['role' => 'member']);
    $collection = Collection::factory()->for($owner->company())->create();
    $document = Document::factory()->for($collection)->create();

    $response = $this->actingAs($member)->post(route('documents.store', $collection), [
        'title' => 'Sem permissão',
        'content' => 'Não pode criar.',
    ]);

    $response->assertForbidden();
});

it('uploads a Latin-1 text file, stores the original and indexes normalized content', function () {
    config(['queue.default' => 'sync', 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $latin1Content = mb_convert_encoding("Política de troca\n\nAção em até 7 dias.", 'ISO-8859-1', 'UTF-8');

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Política de troca',
        'file' => UploadedFile::fake()->createWithContent('politica.txt', $latin1Content),
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::File)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toBe("Política de troca\n\nAção em até 7 dias.")
        ->and($document->size_bytes)->toBe(strlen($latin1Content))
        ->and($document->original_filename)->toBe('politica.txt')
        ->and($document->mime_type)->toBe('text/plain');
    Storage::disk('documents')->assertExists($document->file_path);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('Ação em até 7 dias.'));
});

it('extracts DOCX headings and table rows before indexing the document', function () {
    config(['queue.default' => 'sync', 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Manual DOCX',
        'file' => UploadedFile::fake()->createWithContent('manual.docx', docxContent()),
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('# Procedimento de trocas')
        ->and($document->content)->toContain('Prazo | 7 dias')
        ->and($document->content)->toContain('Canal | E-mail');
});

it('marks an image-only PDF as failed with the extraction message', function () {
    config(['queue.default' => 'sync', 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'PDF escaneado',
        'file' => UploadedFile::fake()->createWithContent('escaneado.pdf', pdfContent('', true)),
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('PDF sem texto extraível');
    Storage::disk('documents')->assertExists($document->file_path);
});

it('extracts text from a PDF and indexes it', function () {
    config(['queue.default' => 'sync', 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'PDF textual',
        'file' => UploadedFile::fake()->createWithContent('manual.pdf', pdfContent('Arquivo PDF')),
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('Arquivo PDF');
});

it('replaces an uploaded file, recalculates quota and removes the previous original', function () {
    config(['queue.default' => 'sync', 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Manual',
        'file' => UploadedFile::fake()->createWithContent('manual.txt', 'Versão anterior.'),
    ]);
    $document = Document::query()->firstOrFail();
    $oldPath = $document->file_path;
    $newContent = 'Versão atualizada com mais conteúdo.';

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'title' => 'Manual atualizado',
        'file' => UploadedFile::fake()->createWithContent('manual-atualizado.txt', $newContent),
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->original_filename)->toBe('manual-atualizado.txt')
        ->and($document->content)->toBe($newContent)
        ->and($document->size_bytes)->toBe(strlen($newContent));
    Storage::disk('documents')->assertMissing($oldPath);
    Storage::disk('documents')->assertExists($document->file_path);
});

it('downloads an original file and hides another company file', function () {
    config(['knowledge.files_disk' => 'documents']);
    Storage::fake('documents');

    $owner = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($owner->company())->create();
    $document = Document::factory()->for($collection)->create([
        'type' => DocumentType::File,
        'content' => 'Conteúdo extraído.',
        'file_path' => 'documents/'.$owner->company()->id.'/arquivo.txt',
        'original_filename' => 'arquivo.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 18,
        'status' => DocumentStatus::Ready,
    ]);
    Storage::disk('documents')->put($document->file_path, 'conteúdo original');

    $response = $this->actingAs($owner)->get(route('documents.download', $document));

    $response->assertDownload('arquivo.txt');

    $otherUser = User::factory()->withTeam()->create();
    $this->actingAs($otherUser)
        ->get(route('documents.download', $document))
        ->assertNotFound();
});

it('rejects an unsupported uploaded file', function () {
    Storage::fake('documents');
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Formato inválido',
        'file' => UploadedFile::fake()->create('arquivo.exe', 1, 'application/octet-stream'),
    ]);

    $response->assertSessionHasErrors(['file' => 'Envie um arquivo PDF, DOCX ou TXT.']);
    $this->assertDatabaseCount('documents', 0);
});

it('rejects a file whose extension does not match its valid MIME type', function () {
    Storage::fake('documents');
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Extensão inválida',
        'file' => UploadedFile::fake()->create('arquivo.exe', 1, 'text/plain'),
    ]);

    $response->assertSessionHasErrors(['file' => 'A extensão do arquivo deve ser PDF, DOCX ou TXT.']);
    $this->assertDatabaseCount('documents', 0);
});

it('rejects an oversized uploaded file', function () {
    Storage::fake('documents');
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Arquivo grande',
        'file' => UploadedFile::fake()->create('arquivo.pdf', 20 * 1024 + 1, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors(['file' => 'O arquivo deve ter no máximo 20 MB.']);
    $this->assertDatabaseCount('documents', 0);
});

it('rejects a file that would exceed the company storage quota', function () {
    config(['knowledge.storage_quota_bytes' => 3, 'knowledge.files_disk' => 'documents']);
    Storage::fake('documents');
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'file',
        'title' => 'Arquivo acima da cota',
        'file' => UploadedFile::fake()->createWithContent('arquivo.txt', '1234'),
    ]);

    $response->assertSessionHasErrors([
        'file' => 'O conteúdo ultrapassa a cota de armazenamento de 500 MB da Empresa.',
    ]);
    $this->assertDatabaseCount('documents', 0);
});

it('requires authentication to download an original file', function () {
    config(['knowledge.files_disk' => 'documents']);
    Storage::fake('documents');

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'type' => DocumentType::File,
        'file_path' => 'documents/'.$user->company()->id.'/arquivo.txt',
        'original_filename' => 'arquivo.txt',
    ]);
    Storage::disk('documents')->put($document->file_path, 'conteúdo original');

    $this->get(route('documents.download', $document))->assertRedirect(route('login'));
});

function docxContent(): string
{
    $path = tempnam(sys_get_temp_dir(), 'document-test-');
    expect($path)->not->toBeFalse();

    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();
    $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Procedimento de trocas</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Prazo</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>7 dias</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Canal</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>E-mail</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:sectPr/></w:body></w:document>');
    $archive->close();
    $contents = file_get_contents($path);
    unlink($path);

    expect($contents)->toBeString();

    return $contents;
}

function pdfContent(string $text = '', bool $imageOnly = false): string
{
    if ($imageOnly) {
        $imageData = "\xFF\x00\x00";
        $stream = "q 100 0 0 100 100 100 cm /Im1 Do Q\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream.'endstream',
            '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length '.strlen($imageData).' >>'."\nstream\n".$imageData.'endstream',
        ];
    } else {
        $stream = 'BT /F1 12 Tf 72 720 Td ('.$text.") Tj ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1).' /Root 1 0 R >>\nstartxref\n'.$xrefOffset."\n%%EOF\n";
}
