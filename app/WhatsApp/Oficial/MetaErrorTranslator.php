<?php

namespace App\WhatsApp\Oficial;

final class MetaErrorTranslator
{
    public function translate(?array $error): string
    {
        $code = (int) ($error['code'] ?? 0);
        $type = (string) ($error['type'] ?? '');

        return match (true) {
            $code === 190 => 'O access token é inválido ou expirou.',
            $code === 10 => 'O token não tem permissão para acessar este número.',
            $code === 100 => 'O Phone Number ID ou os campos informados são inválidos.',
            $code === 133005 => 'O PIN informado está incorreto. Confira os seis dígitos e tente novamente.',
            $type === 'OAuthException' => 'O access token é inválido ou expirou.',
            default => 'A Meta recusou a conexão. Verifique as credenciais e as permissões do aplicativo.',
        };
    }
}
