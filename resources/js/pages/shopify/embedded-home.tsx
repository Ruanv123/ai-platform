import { Head } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Store } from 'lucide-react';
import { Button } from '@/components/ui/button';
import shopify from '@/routes/shopify';

type ShopifyHome = {
    domain: string;
    name: string | null;
    teamName: string | null;
    syncStatus: 'importing' | 'synced' | 'error' | null;
    syncProgress: number | null;
    lastSyncedAt: string | null;
    syncError: string | null;
    suggestedAction: string | null;
};

type Props = {
    status: 'connected' | 'pending' | 'expired';
    shop: ShopifyHome;
    platformUrl: string | null;
};

const syncLabels = {
    importing: 'Importando catálogo',
    synced: 'Sincronizada',
    error: 'Com erro',
};

export default function EmbeddedHome({ status, shop, platformUrl }: Props) {
    const handoffToken =
        typeof window === 'undefined'
            ? null
            : new URLSearchParams(window.location.hash.slice(1)).get('handoff');
    const handoffUrl =
        handoffToken === null
            ? null
            : shopify.install.start.url({ handoffToken });

    return (
        <main className="bg-background text-foreground flex min-h-screen justify-center px-5 py-10 sm:px-8">
            <Head title="Shopify" />
            <section className="flex w-full max-w-2xl flex-col gap-8">
                <header className="flex items-center gap-4">
                    <div className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-xl">
                        <Store className="size-6" aria-hidden="true" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Shopify
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {shop.domain}
                        </p>
                    </div>
                </header>

                {status === 'connected' && (
                    <div className="bg-card flex flex-col gap-6 rounded-xl border p-6 shadow-sm">
                        <div className="flex flex-col gap-2">
                            <span className="text-muted-foreground text-sm">
                                Loja conectada à Empresa
                            </span>
                            <h2 className="text-lg font-semibold">
                                {shop.teamName}
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                {shop.name}
                            </p>
                        </div>
                        <div className="bg-muted/50 flex flex-col gap-3 rounded-lg p-4">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm font-medium">
                                    Estado do catálogo
                                </span>
                                <span className="text-muted-foreground text-sm">
                                    {shop.syncStatus
                                        ? syncLabels[shop.syncStatus]
                                        : 'Aguardando estado'}
                                </span>
                            </div>
                            {shop.syncStatus === 'importing' &&
                                shop.syncProgress !== null && (
                                    <div className="flex flex-col gap-2">
                                        <div className="bg-background h-2 overflow-hidden rounded-full">
                                            <div
                                                className="bg-primary h-full rounded-full transition-[width]"
                                                style={{
                                                    width: `${shop.syncProgress}%`,
                                                }}
                                            />
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            {shop.syncProgress}% concluído
                                        </p>
                                    </div>
                                )}
                            {shop.syncStatus === 'error' && (
                                <div className="text-destructive flex flex-col gap-1 text-sm">
                                    {shop.syncError && <p>{shop.syncError}</p>}
                                    {shop.suggestedAction && (
                                        <p>{shop.suggestedAction}</p>
                                    )}
                                </div>
                            )}
                            {shop.lastSyncedAt && (
                                <p className="text-muted-foreground text-xs">
                                    Última sincronização:{' '}
                                    {new Date(
                                        shop.lastSyncedAt,
                                    ).toLocaleString()}
                                </p>
                            )}
                        </div>
                        {platformUrl && (
                            <Button asChild className="self-start">
                                <a
                                    href={platformUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Abrir plataforma{' '}
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                        )}
                    </div>
                )}

                {status === 'pending' && (
                    <div className="bg-card flex flex-col gap-5 rounded-xl border p-6 shadow-sm">
                        <div className="flex flex-col gap-2">
                            <h2 className="text-lg font-semibold">
                                Conectar a uma Empresa
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                A instalação foi validada. Escolha uma Empresa
                                na plataforma para vincular esta loja.
                            </p>
                        </div>
                        {handoffUrl ? (
                            <Button asChild className="self-start">
                                <a
                                    href={handoffUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Abrir plataforma{' '}
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                        ) : (
                            <div className="flex flex-col items-start gap-3">
                                <p className="text-muted-foreground text-sm">
                                    Este link de instalação já foi usado ou
                                    expirou. Reabra o app para gerar outro.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        window.location.assign(
                                            shopify.embedded.bootstrap.url(),
                                        )
                                    }
                                >
                                    <RefreshCw className="size-4" /> Revalidar
                                    instalação
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {status === 'expired' && (
                    <div className="bg-card flex flex-col items-start gap-4 rounded-xl border p-6 shadow-sm">
                        <h2 className="text-lg font-semibold">
                            A instalação precisa ser revalidada
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Abra novamente o app no admin da Shopify para
                            continuar.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                window.location.assign(
                                    shopify.embedded.bootstrap.url(),
                                )
                            }
                        >
                            <RefreshCw className="size-4" /> Revalidar
                            instalação
                        </Button>
                    </div>
                )}
            </section>
        </main>
    );
}
