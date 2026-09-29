<?php

namespace App\Ai\Tools;

use App\Crm\CrmCardLookup;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ConsultCrmCard implements Tool
{
    public function __construct(
        private Team $team,
        private ?Contact $contact,
        private CrmCardLookup $lookup,
        private ?string $playgroundPhone = null,
    ) {}

    public function description(): string
    {
        return 'Consulte a ficha real deste Contato no CRM para responder com contexto comercial atualizado. A consulta mostra apenas os Campos do CRM liberados pela Empresa, Negócios abertos e o nome do Responsável. Nunca use informações de atividades ou anotações.';
    }

    public function handle(Request $request): string
    {
        return json_encode(
            $this->lookup->lookup($this->team, $this->contact, $this->playgroundPhone),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function name(): string
    {
        return 'consultar_ficha_crm';
    }
}
