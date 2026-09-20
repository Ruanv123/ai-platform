<?php

namespace App\WhatsApp\Output;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\WhatsApp\Oficial\MetaOutputAdapter;
use App\WhatsApp\Testes\EvolutionGoOutputAdapter;
use InvalidArgumentException;

final class WhatsAppOutputAdapterFactory
{
    public function make(Channel $channel): WhatsAppOutputAdapter
    {
        return match ($channel->type) {
            ChannelType::WhatsApp => new MetaOutputAdapter,
            ChannelType::WhatsAppTest => new EvolutionGoOutputAdapter,
            default => throw new InvalidArgumentException(
                "O Tipo de canal [{$channel->type->value}] não possui uma saída de WhatsApp.",
            ),
        };
    }
}
