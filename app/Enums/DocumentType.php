<?php

namespace App\Enums;

enum DocumentType: string
{
    case File = 'file';
    case Text = 'text';
    case Faq = 'faq';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Arquivo',
            self::Text => 'Texto livre',
            self::Faq => 'FAQ',
            self::Url => 'URL',
        };
    }
}
