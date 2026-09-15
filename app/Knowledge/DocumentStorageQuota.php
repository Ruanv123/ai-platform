<?php

namespace App\Knowledge;

use App\Models\Document;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

class DocumentStorageQuota
{
    public function usage(Team $team): int
    {
        return (int) Document::withoutGlobalScopes()
            ->where('team_id', $team->getKey())
            ->sum('size_bytes');
    }

    public function limit(): int
    {
        return (int) config('knowledge.storage_quota_bytes', 500 * 1024 * 1024);
    }

    public function assertCanStore(
        Team $team,
        int $sizeBytes,
        ?Document $replacing = null,
        string $attribute = 'content',
    ): void {
        $usage = $this->usage($team);

        if ($replacing !== null) {
            $usage -= $replacing->size_bytes;
        }

        if ($usage + $sizeBytes <= $this->limit()) {
            return;
        }

        throw ValidationException::withMessages([
            $attribute => 'O conteúdo ultrapassa a cota de armazenamento de 500 MB da Empresa.',
        ]);
    }
}
