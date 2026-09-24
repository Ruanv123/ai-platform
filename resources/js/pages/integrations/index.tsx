import { Head, Link } from '@inertiajs/react';
import { Check, Plug, Settings } from 'lucide-react';
import { useState } from 'react';
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
import { show } from '@/routes/integrations';
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
    status: string;
    statusLabel: string;
    statusMessage: string | null;
};

type IntegrationType = 'crm' | 'ecommerce' | 'agenda';

type Props = {
    crmCatalog: CatalogIntegration[];
    ecommerceCatalog: CatalogIntegration[];
    agendaCatalog: CatalogIntegration[];
    singletonIntegrations: Record<IntegrationType, ConfiguredIntegration | null>;
    permissions: TeamPermissions;
};

const tabs = [
    { value: 'crm', label: 'CRM', type: 'crm' as IntegrationType },
    {
        value: 'ecommerce',
        label: 'E-commerce',
        type: 'ecommerce' as IntegrationType,
    },
    { value: 'outros', label: 'Outros', type: 'agenda' as IntegrationType },
] as const;

type TabValue = (typeof tabs)[number]['value'];

export default function IntegrationsPage({
    crmCatalog,
    ecommerceCatalog,
    agendaCatalog,
    singletonIntegrations,
    permissions,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabValue>('crm');
    const activeTabConfig = tabs.find((tab) => tab.value === activeTab)!;
    const activeType = activeTabConfig.type;
    const catalog =
        activeType === 'crm'
            ? crmCatalog
            : activeType === 'ecommerce'
              ? ecommerceCatalog
              : agendaCatalog;
    const singleton = singletonIntegrations[activeType];

    return (
        <>
            <Head title="Integrações" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <Heading
                    title="Integrações"
                    description="Conecte a Empresa ao que precisa acontecer fora da plataforma."
                />

                <div
                    className="border-border flex gap-6 border-b"
                    role="tablist"
                    aria-label="Tipo de integração"
                >
                    {tabs.map((tab) => (
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

                {singleton ? (
                    <ConnectedCard
                        type={activeType}
                        integration={singleton}
                        catalog={catalog}
                    />
                ) : (
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
                                    <div className="flex items-start justify-between gap-4">
                                        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                                            {item.logo}
                                        </span>
                                        <Badge
                                            variant={
                                                item.available
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {item.available
                                                ? 'Disponível'
                                                : 'Planejado'}
                                        </Badge>
                                    </div>
                                    <CardTitle className="pt-2">
                                        {item.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {item.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {item.available &&
                                    permissions.canManageIntegrations ? (
                                        <Button className="w-full" asChild>
                                            <Link
                                                href={show(activeType, {
                                                    query: {
                                                        provider: item.value,
                                                    },
                                                })}
                                            >
                                                <Plug /> Conectar
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span className="text-muted-foreground text-sm">
                                            {item.available
                                                ? 'Você não tem permissão para conectar.'
                                                : 'Estamos preparando esta conexão.'}
                                        </span>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function ConnectedCard({
    type,
    integration,
    catalog,
}: {
    type: IntegrationType;
    integration: ConfiguredIntegration;
    catalog: CatalogIntegration[];
}) {
    const catalogItem = catalog.find(
        (item) => item.value === integration.provider,
    );

    return (
        <Card className="max-w-lg">
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                            {catalogItem?.logo ?? 'IN'}
                        </span>
                        <div>
                            <CardTitle>
                                {catalogItem?.name ?? integration.name}
                            </CardTitle>
                            <CardDescription>
                                {integration.name}
                            </CardDescription>
                        </div>
                    </div>
                    <StatusBadge
                        status={integration.status}
                        label={integration.statusLabel}
                    />
                </div>
            </CardHeader>
            <CardContent className="flex items-center justify-between gap-3">
                <span className="text-muted-foreground text-xs">
                    {integration.statusMessage ?? 'Ainda não testada'}
                </span>
                <Button variant="outline" asChild>
                    <Link href={show(type)}>
                        <Settings /> Configurar
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    return (
        <Badge
            variant={
                status === 'connected'
                    ? 'success'
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
