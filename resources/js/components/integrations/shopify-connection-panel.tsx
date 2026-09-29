import { router } from '@inertiajs/react';
import { Check, ExternalLink, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import shopifyRoutes from '@/routes/integrations/shopify';
import { destroy } from '@/routes/integrations';
import type { TeamPermissions } from '@/types';

type ShopifyIntegration = {
    id: string;
    name: string;
    type: string;
    provider: string | null;
    status: string;
    statusLabel: string;
    statusMessage: string | null;
};

type ShopifyStore = {
    id: string;
    domain: string;
    name: string;
    currencyCode: string;
    countryCode: string | null;
    timezone: string | null;
    syncStatus: string;
    syncStatusLabel: string;
    syncProgress: number;
    lastSyncedAt: string | null;
    syncError: string | null;
    suggestedAction: string | null;
};

type Props = {
    integration: ShopifyIntegration | null;
    shop: ShopifyStore | null;
    shopifyError: string | null;
    shopifySuccess: string | null;
    permissions: TeamPermissions;
};

export default function ShopifyConnectionPanel({
    integration,
    shop,
    shopifyError,
    shopifySuccess,
    permissions,
}: Props) {
    const [domain, setDomain] = useState(shop?.domain ?? '');
    const canManage = permissions.canManageIntegrations;
    const isConnected = integration?.provider === 'shopify' && shop !== null;

    return (
        <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <div className="flex items-start gap-3">
                <div className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                    SH
                </div>
                <div className="grid gap-1">
                    <h1 className="text-2xl font-semibold">Shopify</h1>
                    <p className="text-muted-foreground text-sm">
                        Conecte o catálogo da sua loja à Empresa com acesso somente de leitura.
                    </p>
                </div>
            </div>

            {shopifyError && (
                <div className="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border p-4 text-sm">
                    {shopifyError}
                </div>
            )}
            {shopifySuccess && (
                <div className="border-border bg-muted/40 text-foreground rounded-lg border p-4 text-sm">
                    {shopifySuccess}
                </div>
            )}

            {isConnected && (
                <Card className="max-w-3xl">
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="grid gap-1">
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {shop.name}
                                <Badge variant={shop.syncStatus === 'error' ? 'destructive' : 'success'}>
                                    {shop.syncStatus === 'synced' && <Check className="size-3" />}
                                    {shop.syncStatusLabel}
                                </Badge>
                            </CardTitle>
                            <CardDescription>{shop.domain}</CardDescription>
                        </div>
                        <a
                            href={`https://${shop.domain}/admin`}
                            target="_blank"
                            rel="noreferrer"
                            className="text-muted-foreground hover:text-foreground inline-flex min-h-10 items-center gap-2 text-sm"
                        >
                            Admin da Shopify <ExternalLink className="size-4" />
                        </a>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground text-xs">Moeda</dt>
                                <dd className="text-sm font-medium">{shop.currencyCode}</dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground text-xs">País</dt>
                                <dd className="text-sm font-medium">{shop.countryCode ?? 'Não informado'}</dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground text-xs">Fuso horário</dt>
                                <dd className="text-sm font-medium">{shop.timezone ?? 'Não informado'}</dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground text-xs">Última sincronização</dt>
                                <dd className="text-sm font-medium">
                                    {shop.lastSyncedAt
                                        ? new Date(shop.lastSyncedAt).toLocaleString('pt-BR')
                                        : 'Aguardando a primeira importação'}
                                </dd>
                            </div>
                        </dl>
                        {(shop.syncError || shop.suggestedAction) && (
                            <div className="border-destructive/30 bg-destructive/5 grid gap-1 rounded-lg border p-4 text-sm">
                                {shop.syncError && <p className="text-destructive">{shop.syncError}</p>}
                                {shop.suggestedAction && <p className="text-muted-foreground">{shop.suggestedAction}</p>}
                            </div>
                        )}
                        <div className="flex flex-wrap gap-2 border-t pt-5">
                            {canManage && (
                                <Button asChild>
                                    <a href={shopifyRoutes.connect({ query: { shop: shop.domain } }).url}>
                                        Reconectar
                                    </a>
                                </Button>
                            )}
                            {canManage && integration && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.delete(destroy(integration.id).url)}
                                >
                                    <Trash2 /> Desconectar
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {!isConnected && (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Conectar uma loja</CardTitle>
                        <CardDescription>
                            Informe o domínio myshopify.com. Você autorizará o acesso de leitura na Shopify.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {canManage ? (
                            <form
                                id="shopify-connect-form"
                                method="GET"
                                action={shopifyRoutes.connect().url}
                                className="grid gap-4"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="shopify-domain">Domínio da loja</Label>
                                    <Input
                                        id="shopify-domain"
                                        name="shop"
                                        value={domain}
                                        onChange={(event) => setDomain(event.target.value)}
                                        placeholder="minha-loja.myshopify.com"
                                        autoComplete="url"
                                        required
                                    />
                                </div>
                                <Button type="submit" className="w-fit">
                                    Conectar com Shopify
                                </Button>
                            </form>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Você não tem permissão para gerenciar Integrações.
                            </p>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
