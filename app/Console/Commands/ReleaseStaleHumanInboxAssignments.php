<?php

namespace App\Console\Commands;

use App\Inbox\HumanInboxService;
use Illuminate\Console\Command;

final class ReleaseStaleHumanInboxAssignments extends Command
{
    protected $signature = 'inbox:release-stale';

    protected $description = 'Libera atribuições de atendimento humano sem atividade recente.';

    public function handle(HumanInboxService $inbox): int
    {
        $released = $inbox->releaseStaleAssignments();
        $this->info("{$released} atribuição(ões) liberada(s).");

        return self::SUCCESS;
    }
}
