import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, RefreshCw, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
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
import {
    destroy,
    index,
    show,
    store,
    test,
    update,
} from '@/routes/integrations';
import type { TeamPermissions } from '@/types';

type CatalogIntegration = {
    value: string;
    name: string;
    description: string;
    logo: string;
    capabilities: string[];
    planned: boolean;
    available: boolean;
};

type ConfiguredIntegration = {
    id: string;
    name: string;
    type: string;
    provider: string | null;
    baseUrl: string | null;
    status: string;
    statusLabel: string;
    statusMessage: string | null;
};

type IntegrationType = 'crm' | 'ecommerce' | 'agenda';

type Props = {
    type: IntegrationType;
    catalog: CatalogIntegration[];
    provider: string | null;
    integration: ConfiguredIntegration | null;
    permissions: TeamPermissions;
};

const settingsTabs = [
    { value: 'settings', label: 'Configurações' },
    { value: 'data', label: 'Dados' },
] as const;

type SettingsTab = (typeof settingsTabs)[number]['value'];

export default function IntegrationShowPage({
    type,
    catalog,
    provider,
    integration,
    permissions,
}: Props) {
    const [activeTab, setActiveTab] = useState<SettingsTab>('settings');
    const selectedProviderValue = integration?.provider ?? provider;
    const selectedCatalogItem = catalog.find(
        (item) => item.value === selectedProviderValue,
    );

    const title = integration
        ? (selectedCatalogItem?.name ?? integration.name)
        : (selectedCatalogItem?.name ?? 'Conectar integração');

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div className="flex items-start gap-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label="Voltar para integrações"
                    >
                        <Link href={index()}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div className="flex flex-1 items-start justify-between gap-4">
                        <Heading
                            title={title}
                            description={
                                selectedCatalogItem?.description ??
                                'A credencial é cifrada e nunca aparece no desenho do Fluxo.'
                            }
                        />
                        {integration && (
                            <StatusBadge
                                status={integration.status}
                                label={integration.statusLabel}
                            />
                        )}
                    </div>
                </div>

                {!selectedCatalogItem ? (
                    <ProviderPicker type={type} catalog={catalog} />
                ) : (
                    <div className="grid gap-5">
                        <div
                            className="border-border flex gap-6 border-b"
                            role="tablist"
                            aria-label="Configurações da integração"
                        >
                            {settingsTabs.map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === tab.value}
                                    onClick={() => setActiveTab(tab.value)}
                                    className={`border-b-2 px-1 pb-3 text-sm font-medium transition-colors ${activeTab === tab.value ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {activeTab === 'settings' ? (
                            <SettingsForm
                                type={type}
                                provider={selectedCatalogItem.value}
                                integration={integration}
                                permissions={permissions}
                            />
                        ) : (
                            <Card className="border-dashed shadow-none">
                                <CardContent className="text-muted-foreground flex min-h-40 items-center justify-center p-6 text-center text-sm">
                                    Em breve: visualize aqui os dados
                                    trazidos por esta integração.
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function ProviderPicker({
    type,
    catalog,
}: {
    type: IntegrationType;
    catalog: CatalogIntegration[];
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {catalog.map((item) => (
                <Card
                    key={item.value}
                    className={
                        item.available
                            ? 'group hover:border-primary/50'
                            : 'bg-muted/20 border-dashed shadow-none'
                    }
                >
                    <CardHeader className="flex-1">
                        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                            {item.logo}
                        </span>
                        <CardTitle className="pt-2">{item.name}</CardTitle>
                        <CardDescription>{item.description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {item.available ? (
                            <Button className="w-full" asChild>
                                <Link
                                    href={show(type, {
                                        query: { provider: item.value },
                                    })}
                                >
                                    Conectar
                                </Link>
                            </Button>
                        ) : (
                            <span className="text-muted-foreground text-sm">
                                Estamos preparando esta conexão.
                            </span>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function SettingsForm({
    type,
    provider,
    integration,
    permissions,
}: {
    type: IntegrationType;
    provider: string;
    integration: ConfiguredIntegration | null;
    permissions: TeamPermissions;
}) {
    const [name, setName] = useState(integration?.name ?? '');
    const [baseUrl, setBaseUrl] = useState(integration?.baseUrl ?? '');
    const [token, setToken] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (name.trim() === '' || !permissions.canManageIntegrations) {
            return;
        }
        setIsSaving(true);
        const payload = {
            name: name.trim(),
            type,
            provider,
            base_url: baseUrl,
            auth_type: token ? 'bearer' : integration === null ? 'none' : null,
            token: token || null,
        };
        const request =
            integration === null
                ? router.post(store().url, payload, {
                      onFinish: () => setIsSaving(false),
                  })
                : router.patch(update(integration.id).url, payload, {
                      onFinish: () => setIsSaving(false),
                  });

        return request;
    };

    return (
        <Card>
            <form onSubmit={submit}>
                <CardContent className="grid gap-4 pt-6">
                    <div className="grid gap-2">
                        <Label htmlFor="integration-name">
                            Nome da conexão
                        </Label>
                        <Input
                            id="integration-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="integration-url">URL base</Label>
                        <Input
                            id="integration-url"
                            type="url"
                            placeholder="https://api.exemplo.com (deixe vazio para manter)"
                            value={baseUrl}
                            onChange={(event) =>
                                setBaseUrl(event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="integration-token">
                            Token (opcional)
                        </Label>
                        <Input
                            id="integration-token"
                            type="password"
                            value={token}
                            onChange={(event) => setToken(event.target.value)}
                        />
                    </div>
                </CardContent>
                <div className="flex items-center justify-between gap-2 border-t p-6">
                    <div className="flex gap-2">
                        {integration && permissions.canManageIntegrations && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(test(integration.id).url)
                                    }
                                >
                                    <RefreshCw /> Testar conexão
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            destroy(integration.id).url,
                                        )
                                    }
                                >
                                    <Trash2 /> Remover
                                </Button>
                            </>
                        )}
                    </div>
                    <Button
                        type="submit"
                        disabled={
                            isSaving || !permissions.canManageIntegrations
                        }
                    >
                        {isSaving
                            ? 'Salvando...'
                            : integration === null
                              ? 'Conectar'
                              : 'Salvar alterações'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    return (
        <Badge
            variant={
                status === 'connected'
                    ? 'default'
                    : status === 'error'
                      ? 'destructive'
                      : 'secondary'
            }
        >
            {status === 'connected' && <Check className="size-3" />}
            {label}
        </Badge>
    );
}
