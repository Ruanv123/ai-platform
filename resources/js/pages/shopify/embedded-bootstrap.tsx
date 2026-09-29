import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useMountEffect } from '@/hooks/use-mount-effect';

type Props = {
    authenticateUrl: string;
};

let authenticationPromise: Promise<void> | null = null;

function authenticateShopifySession(authenticateUrl: string): Promise<void> {
    if (authenticationPromise !== null) {
        return authenticationPromise;
    }

    authenticationPromise = (async () => {
        let isRetry = false;

        while (true) {
            const idToken = await window.shopify.idToken();
            const response = await fetch(authenticateUrl, {
                method: 'POST',
                credentials: 'omit',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${idToken}`,
                },
            });

            if (
                !isRetry &&
                response.status === 401 &&
                response.headers.get(
                    'X-Shopify-Retry-Invalid-Session-Request',
                ) === '1'
            ) {
                isRetry = true;
                continue;
            }

            if (!response.ok) {
                throw new Error('Shopify session validation failed.');
            }

            const result = (await response.json()) as { homeUrl: string };
            window.location.replace(result.homeUrl);

            return;
        }
    })().catch(() => {
        authenticationPromise = null;

        throw new Error('Shopify session validation failed.');
    });

    return authenticationPromise;
}

export default function EmbeddedBootstrap({ authenticateUrl }: Props) {
    const [error, setError] = useState<string | null>(null);

    useMountEffect(() => {
        let cancelled = false;

        void authenticateShopifySession(authenticateUrl).catch(() => {
            if (!cancelled) {
                setError(
                    'Não foi possível validar a sessão da Shopify. Reabra o app e tente novamente.',
                );
            }
        });

        return () => {
            cancelled = true;
        };
    });

    return (
        <main className="bg-background text-foreground flex min-h-screen items-center justify-center px-6 py-12">
            <Head title="Abrindo Shopify" />
            <div className="flex w-full max-w-md flex-col items-center gap-5 text-center">
                <div
                    className="bg-primary/10 size-12 animate-pulse rounded-xl"
                    aria-hidden="true"
                />
                <div className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold">
                        Conectando à Shopify
                    </h1>
                    <p
                        className="text-muted-foreground text-sm"
                        aria-live="polite"
                    >
                        {error ??
                            'Validando a instalação antes de carregar o app.'}
                    </p>
                </div>
                {error && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.location.reload()}
                    >
                        Tentar novamente
                    </Button>
                )}
            </div>
        </main>
    );
}
