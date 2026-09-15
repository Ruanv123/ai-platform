const genericPasskeyError =
    'Não foi possível concluir a operação com a chave de acesso.';

export function translatePasskeyError(error: string): string {
    if (error === 'The passkey operation was cancelled.') {
        return 'A operação da chave de acesso foi cancelada.';
    }

    if (error === 'This device is already registered as a passkey.') {
        return 'Este dispositivo já está cadastrado como chave de acesso.';
    }

    if (error === 'Passkeys are not supported in this browser.') {
        return 'Chaves de acesso não são compatíveis com este navegador.';
    }

    if (error === 'An unknown error occurred.') {
        return 'Ocorreu um erro desconhecido.';
    }

    const invalidDomain = error.match(
        /^Passkeys can't be used on (.+)\. For local development, use localhost\.$/,
    );

    if (invalidDomain) {
        return `Chaves de acesso não podem ser usadas em ${invalidDomain[1]}. Para desenvolvimento local, use localhost.`;
    }

    return genericPasskeyError;
}
