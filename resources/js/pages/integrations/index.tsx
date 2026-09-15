import { Head } from '@inertiajs/react';
import { ArrowRight, Check, Plug } from 'lucide-react';
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
};

function IntegrationMark({ logo }: { logo: string }) {
    return (
        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold tracking-tight shadow-sm">
            {logo}
        </span>
    );
}

export default function Integrations({ integrations }: Props) {
    const [selectedIntegration, setSelectedIntegration] =
        useState<Integration | null>(null);

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
                    description="Conecte os dados dos seus atendimentos ao CRM da sua Empresa."
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {integrations.map((integration) => (
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
                                    <IntegrationMark logo={integration.logo} />
                                    <Badge variant="secondary">Em breve</Badge>
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
