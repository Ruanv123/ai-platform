import { Head } from '@inertiajs/react';
import { ArrowRight, Check, Plug, ShoppingBag } from 'lucide-react';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Integration = {
    value: string;
    name: string;
    description: string;
    logo: string;
    capabilities: string[];
};

type Props = {
    integrations: Integration[];
    ecommerceIntegrations: Integration[];
};

const integrationTabs = [
    { value: 'crm', label: 'CRMs' },
    { value: 'ecommerce', label: 'Provedores de e-commerce' },
] as const;

function IntegrationMark({ logo }: { logo: string }) {
    return (
        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold tracking-tight shadow-sm">
            {logo}
        </span>
    );
}

export default function Integrations({
    integrations,
    ecommerceIntegrations,
}: Props) {
    const [activeTab, setActiveTab] =
        useState<(typeof integrationTabs)[number]['value']>('crm');
    const [selectedIntegration, setSelectedIntegration] =
        useState<Integration | null>(null);

    const activeIntegrations =
        activeTab === 'crm' ? integrations : ecommerceIntegrations;
    const activeTabIndex = integrationTabs.findIndex(
        (tab) => tab.value === activeTab,
    );

    const selectTabByIndex = (index: number) => {
        setActiveTab(integrationTabs[index].value);
    };

    const openDetails = (integration: Integration) => {
        setSelectedIntegration(integration);
    };

    const closeDetails = (open: boolean) => {
        if (!open) {
            setSelectedIntegration(null);
        }
    };

    return (
        <>
            <Head title="Integrações" />

            <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4 md:p-6">
                <Heading
                    title="Integrações"
                    description="Conecte os dados dos seus atendimentos às ferramentas da sua Empresa."
                />

                <div className="grid gap-6">
                    <div
                        aria-label="Tipo de integração"
                        className="border-border flex gap-6 border-b"
                        role="tablist"
                    >
                        {integrationTabs.map((tab) => {
                            const isActive = activeTab === tab.value;

                            return (
                                <button
                                    key={tab.value}
                                    type="button"
                                    id={`integration-tab-${tab.value}`}
                                    aria-selected={isActive}
                                    aria-controls="integrations-panel"
                                    className={`focus-visible:ring-ring relative -mb-px flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none ${
                                        isActive
                                            ? 'border-primary text-foreground'
                                            : 'text-muted-foreground hover:border-border hover:text-foreground border-transparent'
                                    }`}
                                    onClick={() => setActiveTab(tab.value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'ArrowRight') {
                                            event.preventDefault();
                                            selectTabByIndex(
                                                (activeTabIndex + 1) %
                                                    integrationTabs.length,
                                            );
                                        }

                                        if (event.key === 'ArrowLeft') {
                                            event.preventDefault();
                                            selectTabByIndex(
                                                (activeTabIndex -
                                                    1 +
                                                    integrationTabs.length) %
                                                    integrationTabs.length,
                                            );
                                        }

                                        if (event.key === 'Home') {
                                            event.preventDefault();
                                            selectTabByIndex(0);
                                        }

                                        if (event.key === 'End') {
                                            event.preventDefault();
                                            selectTabByIndex(
                                                integrationTabs.length - 1,
                                            );
                                        }
                                    }}
                                    role="tab"
                                    tabIndex={isActive ? 0 : -1}
                                >
                                    {tab.value === 'crm' ? (
                                        <Plug className="size-4" />
                                    ) : (
                                        <ShoppingBag className="size-4" />
                                    )}
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>

                    <div
                        id="integrations-panel"
                        aria-label={`Integrações de ${activeTab === 'crm' ? 'CRM' : 'e-commerce'}`}
                        aria-live="polite"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                        aria-labelledby={`integration-tab-${activeTab}`}
                        role="tabpanel"
                    >
                        {activeIntegrations.map((integration) => (
                            <Card
                                key={integration.value}
                                role="button"
                                tabIndex={0}
                                aria-label={`Ver detalhes de ${integration.name}`}
                                className="group hover:border-primary/50 hover:bg-muted/30 focus-visible:ring-ring flex cursor-pointer flex-col transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                onClick={() => openDetails(integration)}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        event.preventDefault();
                                        openDetails(integration);
                                    }
                                }}
                            >
                                <CardHeader className="flex-1">
                                    <div className="flex items-start justify-between gap-4">
                                        <IntegrationMark
                                            logo={integration.logo}
                                        />
                                        <Badge variant="secondary">
                                            Em breve
                                        </Badge>
                                    </div>
                                    <CardTitle className="pt-2">
                                        {integration.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {integration.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-muted-foreground group-hover:text-foreground flex items-center gap-2 text-sm font-medium transition-colors">
                                        Ver detalhes
                                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>

            <Sheet
                open={selectedIntegration !== null}
                onOpenChange={closeDetails}
            >
                <SheetContent className="w-full gap-0 sm:max-w-lg">
                    {selectedIntegration !== null && (
                        <>
                            <SheetHeader className="gap-4 border-b p-6 pr-12">
                                <div className="flex items-center gap-3">
                                    <IntegrationMark
                                        logo={selectedIntegration.logo}
                                    />
                                    <div className="grid gap-1">
                                        <SheetTitle>
                                            {selectedIntegration.name}
                                        </SheetTitle>
                                        <Badge
                                            variant="secondary"
                                            className="w-fit"
                                        >
                                            Em breve
                                        </Badge>
                                    </div>
                                </div>
                                <SheetDescription className="text-base leading-relaxed">
                                    {selectedIntegration.description}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="grid gap-4 overflow-y-auto p-6">
                                <div className="flex items-center gap-2">
                                    <Plug className="text-primary size-4" />
                                    <h2 className="text-sm font-semibold">
                                        O que você poderá fazer
                                    </h2>
                                </div>
                                <ul className="grid gap-3">
                                    {selectedIntegration.capabilities.map(
                                        (capability) => (
                                            <li
                                                key={capability}
                                                className="flex items-start gap-3 text-sm"
                                            >
                                                <span className="bg-primary/10 text-primary mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full">
                                                    <Check className="size-3" />
                                                </span>
                                                <span>{capability}</span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>

                            <SheetFooter className="border-t p-6">
                                <Button type="button" disabled>
                                    <Plug />
                                    Conectar
                                </Button>
                                <p className="text-muted-foreground text-center text-xs">
                                    Essa integração estará disponível em breve.
                                </p>
                            </SheetFooter>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
