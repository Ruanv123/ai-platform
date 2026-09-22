import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Globe2,
    KeyRound,
    Pencil,
    Plus,
    Radio,
    RefreshCw,
    Trash2,
    Webhook,
} from 'lucide-react';
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
import { patchJson, postJson } from '@/lib/flow-http';
import { destroy, store, test, update } from '@/routes/integrations';
import {
    destroy as destroyEvent,
    store as storeEvent,
    update as updateEvent,
} from '@/routes/events';
import { store as storeWebhook } from '@/routes/events/webhooks';
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

type FlowEvent = {
    id: string;
    name: string;
    description: string | null;
    payloadSchema: Record<string, unknown> | null;
    emissionsCount: number;
    webhooks: Array<{ id: string; url: string; active: boolean }>;
};

type Props = {
    integrations: CatalogIntegration[];
    ecommerceIntegrations: CatalogIntegration[];
    agendaIntegrations: CatalogIntegration[];
    configuredIntegrations: ConfiguredIntegration[];
    events: FlowEvent[];
    webhookFailures: { count: number; href: string };
    permissions: TeamPermissions;
};

const catalogTabs = [
    { value: 'agenda', label: 'Agenda' },
    { value: 'crm', label: 'CRMs' },
    { value: 'ecommerce', label: 'E-commerce' },
] as const;

type CatalogTab = (typeof catalogTabs)[number]['value'];

export default function IntegrationsPage({
    integrations,
    ecommerceIntegrations,
    agendaIntegrations,
    configuredIntegrations,
    events,
    webhookFailures,
    permissions,
}: Props) {
    const [activeTab, setActiveTab] = useState<CatalogTab>('agenda');
    const [selected, setSelected] = useState<CatalogIntegration | null>(null);
    const [editing, setEditing] = useState<ConfiguredIntegration | null>(null);
    const [name, setName] = useState('');
    const [baseUrl, setBaseUrl] = useState('');
    const [token, setToken] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const activeCatalog =
        activeTab === 'agenda'
            ? agendaIntegrations
            : activeTab === 'crm'
              ? integrations
              : ecommerceIntegrations;

    const openCreate = (catalog: CatalogIntegration) => {
        setEditing(null);
        setSelected(catalog);
        setName(catalog.name);
        setBaseUrl('');
        setToken('');
    };

    const openEdit = (integration: ConfiguredIntegration) => {
        setSelected(null);
        setEditing(integration);
        setName(integration.name);
        setBaseUrl(integration.baseUrl ?? '');
        setToken('');
    };

    const closeForm = () => {
        setSelected(null);
        setEditing(null);
    };

    const submitIntegration = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (
            (selected === null && editing === null) ||
            name.trim() === '' ||
            !permissions.canManageIntegrations
        ) {
            return;
        }
        setIsSaving(true);
        router.post(
            editing === null ? store().url : update(editing.id).url,
            {
                name: name.trim(),
                type:
                    editing?.type ??
                    (selected?.value === 'agenda' ? 'agenda' : activeTab),
                provider: editing?.provider ?? selected?.value,
                base_url: baseUrl,
                auth_type: token ? 'bearer' : editing === null ? 'none' : null,
                token: token || null,
            },
            {
                onFinish: () => setIsSaving(false),
                onSuccess: closeForm,
            },
        );
    };

    return (
        <>
            <Head title="Integrações" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Integrações"
                        description="Conecte a Empresa ao que precisa acontecer fora da plataforma."
                    />
                    {webhookFailures.count > 0 && (
                        <Link
                            href={webhookFailures.href}
                            className="border-destructive/30 bg-destructive/5 text-destructive hover:bg-destructive/10 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
                        >
                            <Webhook className="size-4" />
                            {webhookFailures.count} webhook
                            {webhookFailures.count === 1 ? '' : 's'} com falha
                            <ArrowRight className="size-4" />
                        </Link>
                    )}
                </div>

                <section
                    className="grid gap-4"
                    aria-labelledby="configured-integrations-title"
                >
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h2
                                id="configured-integrations-title"
                                className="text-base font-semibold"
                            >
                                Conexões da Empresa
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                A credencial fica aqui. Os Fluxos só escolhem
                                qual conexão usar.
                            </p>
                        </div>
                    </div>
                    {configuredIntegrations.length === 0 ? (
                        <Card className="border-dashed shadow-none">
                            <CardContent className="flex min-h-28 items-center gap-3 p-6">
                                <KeyRound className="text-muted-foreground size-5" />
                                <p className="text-muted-foreground text-sm">
                                    Nenhuma conexão cadastrada ainda. Comece
                                    pela Agenda abaixo.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {configuredIntegrations.map((integration) => (
                                <Card key={integration.id}>
                                    <CardHeader className="gap-3 pb-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                <span className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                                                    <Globe2 className="size-5" />
                                                </span>
                                                <div>
                                                    <CardTitle className="text-sm">
                                                        {integration.name}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {integration.type}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <StatusBadge
                                                status={integration.status}
                                                label={integration.statusLabel}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex items-center justify-between gap-2">
                                        <span className="text-muted-foreground text-xs">
                                            {integration.statusMessage ??
                                                'Ainda não testada'}
                                        </span>
                                        {permissions.canManageIntegrations && (
                                            <div className="flex gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Editar integração"
                                                    onClick={() =>
                                                        openEdit(integration)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Testar conexão"
                                                    onClick={() =>
                                                        router.post(
                                                            test(integration.id)
                                                                .url,
                                                        )
                                                    }
                                                >
                                                    <RefreshCw className="size-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Apagar integração"
                                                    onClick={() =>
                                                        router.delete(
                                                            destroy(
                                                                integration.id,
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>

                <section className="grid gap-5" aria-labelledby="catalog-title">
                    <div>
                        <h2
                            id="catalog-title"
                            className="text-base font-semibold"
                        >
                            Catálogo
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Só a Agenda conecta hoje. Os demais itens mostram o
                            que está planejado, sem prometer uma capacidade que
                            ainda não existe.
                        </p>
                    </div>
                    <div
                        className="border-border flex gap-6 border-b"
                        role="tablist"
                        aria-label="Tipo de integração"
                    >
                        {catalogTabs.map((tab) => (
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
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {activeCatalog.map((catalog) => (
                            <Card
                                key={catalog.value}
                                className={
                                    catalog.available
                                        ? 'group hover:border-primary/50'
                                        : 'bg-muted/20 border-dashed shadow-none'
                                }
                            >
                                <CardHeader className="flex-1">
                                    <div className="flex items-start justify-between gap-4">
                                        <span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">
                                            {catalog.logo}
                                        </span>
                                        <Badge
                                            variant={
                                                catalog.available
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {catalog.available
                                                ? 'Disponível'
                                                : 'Planejado'}
                                        </Badge>
                                    </div>
                                    <CardTitle className="pt-2">
                                        {catalog.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {catalog.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {catalog.available ? (
                                        <Button
                                            className="w-full"
                                            onClick={() => openCreate(catalog)}
                                            disabled={
                                                !permissions.canManageIntegrations
                                            }
                                        >
                                            <Plus /> Conectar
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
                </section>

                <EventCatalog events={events} permissions={permissions} />
            </div>

            {(selected !== null || editing !== null) && (
                <div className="bg-background/80 fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
                    <Card className="w-full max-w-lg">
                        <CardHeader>
                            <CardTitle>
                                {editing === null
                                    ? `Conectar ${selected?.name}`
                                    : `Editar ${editing.name}`}
                            </CardTitle>
                            <CardDescription>
                                A credencial é cifrada e nunca aparece no
                                desenho do Fluxo.
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={submitIntegration}>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="integration-name">
                                        Nome da conexão
                                    </Label>
                                    <Input
                                        id="integration-name"
                                        value={name}
                                        onChange={(event) =>
                                            setName(event.target.value)
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="integration-url">
                                        URL base
                                    </Label>
                                    <Input
                                        id="integration-url"
                                        type="url"
                                        placeholder="https://agenda.exemplo.com (deixe vazio para manter)"
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
                                        onChange={(event) =>
                                            setToken(event.target.value)
                                        }
                                    />
                                </div>
                            </CardContent>
                            <div className="flex justify-end gap-2 border-t p-6">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={closeForm}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={isSaving}>
                                    {isSaving
                                        ? 'Salvando...'
                                        : editing === null
                                          ? 'Salvar conexão'
                                          : 'Salvar alterações'}
                                </Button>
                            </div>
                        </form>
                    </Card>
                </div>
            )}
        </>
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

function EventCatalog({
    events,
    permissions,
}: {
    events: FlowEvent[];
    permissions: TeamPermissions;
}) {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [payloadSchema, setPayloadSchema] = useState<Record<string, unknown>>(
        {
            type: 'object',
        },
    );
    const [webhookUrls, setWebhookUrls] = useState<Record<string, string>>({});
    const [newSecret, setNewSecret] = useState<string | null>(null);
    const [editingEvent, setEditingEvent] = useState<FlowEvent | null>(null);

    const createEvent = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!permissions.canManageIntegrations || name.trim() === '') return;
        const eventData = {
            name: name.trim(),
            description,
            payload_schema: payloadSchema,
        };
        if (editingEvent === null) {
            await postJson(storeEvent().url, eventData);
        } else {
            await patchJson(updateEvent(editingEvent.id).url, eventData);
        }
        router.reload({ only: ['events'] });
        setName('');
        setDescription('');
        setPayloadSchema({ type: 'object' });
        setEditingEvent(null);
    };

    const createWebhook = async (flowEvent: FlowEvent) => {
        const url = webhookUrls[flowEvent.id]?.trim();
        if (!url) return;
        const response = await postJson<{ secret: string }>(
            storeWebhook(flowEvent.id).url,
            { url },
        );
        setNewSecret(response.secret);
        setWebhookUrls((current) => ({ ...current, [flowEvent.id]: '' }));
        router.reload({ only: ['events'] });
    };

    return (
        <section className="grid gap-5" aria-labelledby="events-title">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <h2 id="events-title" className="text-base font-semibold">
                        Barramento de Eventos
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Declare os nomes uma vez. O Nó de Emitir Evento escolhe
                        daqui e alimenta as métricas.
                    </p>
                </div>
                <Radio className="text-muted-foreground size-5" />
            </div>
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">
                        {editingEvent === null
                            ? 'Declarar Evento'
                            : 'Renomear Evento'}
                    </CardTitle>
                </CardHeader>
                <form onSubmit={createEvent}>
                    <CardContent className="grid gap-3 sm:grid-cols-[1fr_1.4fr_auto]">
                        <Input
                            required
                            pattern="[a-z0-9][a-z0-9._-]*"
                            placeholder="lead.qualificado"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                        />
                        <Input
                            placeholder="Descrição do dado emitido"
                            value={description}
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                        />
                        <EventPayloadSchemaField
                            value={payloadSchema}
                            onChange={setPayloadSchema}
                        />
                        <Button
                            type="submit"
                            disabled={!permissions.canManageIntegrations}
                        >
                            {editingEvent === null ? <Plus /> : null}
                            {editingEvent === null ? 'Adicionar' : 'Salvar'}
                        </Button>
                        {editingEvent !== null && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setEditingEvent(null);
                                    setName('');
                                    setDescription('');
                                    setPayloadSchema({ type: 'object' });
                                }}
                            >
                                Cancelar
                            </Button>
                        )}
                    </CardContent>
                </form>
            </Card>
            {newSecret && (
                <Card className="border-primary/30 bg-primary/5">
                    <CardContent className="grid gap-2 p-4">
                        <p className="text-sm font-semibold">
                            Segredo do webhook — guarde agora
                        </p>
                        <code className="bg-background overflow-x-auto rounded p-2 text-xs">
                            {newSecret}
                        </code>
                        <Button
                            variant="ghost"
                            className="w-fit"
                            onClick={() => setNewSecret(null)}
                        >
                            Fechar
                        </Button>
                    </CardContent>
                </Card>
            )}
            <div className="grid gap-4 md:grid-cols-2">
                {events.map((event) => (
                    <Card key={event.id}>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle className="text-sm">
                                        {event.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {event.description ?? 'Sem descrição'}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    {permissions.canManageIntegrations && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Renomear ${event.name}`}
                                            onClick={() => {
                                                setEditingEvent(event);
                                                setName(event.name);
                                                setDescription(
                                                    event.description ?? '',
                                                );
                                                setPayloadSchema(
                                                    event.payloadSchema ?? {
                                                        type: 'object',
                                                    },
                                                );
                                            }}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                    )}
                                    <Badge variant="outline">
                                        {event.emissionsCount} emitido
                                        {event.emissionsCount === 1 ? '' : 's'}
                                    </Badge>
                                    {permissions.canManageIntegrations && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Apagar ${event.name}`}
                                            onClick={() =>
                                                router.delete(
                                                    destroyEvent(event.id).url,
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <Webhook className="size-4" />{' '}
                                {event.webhooks.length
                                    ? `${event.webhooks.length} destino${event.webhooks.length === 1 ? '' : 's'}`
                                    : 'Sem destino externo'}
                            </div>
                            {permissions.canManageIntegrations && (
                                <div className="flex gap-2">
                                    <Input
                                        type="url"
                                        placeholder="https://seu-sistema/webhook"
                                        value={webhookUrls[event.id] ?? ''}
                                        onChange={(input) =>
                                            setWebhookUrls((current) => ({
                                                ...current,
                                                [event.id]: input.target.value,
                                            }))
                                        }
                                    />
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            void createWebhook(event)
                                        }
                                    >
                                        Adicionar
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}

function EventPayloadSchemaField({
    value,
    onChange,
}: {
    value: Record<string, unknown>;
    onChange: (value: Record<string, unknown>) => void;
}) {
    return (
        <div className="grid gap-2 sm:col-span-2">
            <Label htmlFor="event-payload-schema">
                Formato do dado (JSON Schema)
            </Label>
            <textarea
                id="event-payload-schema"
                className="border-input bg-background min-h-24 rounded-md border p-2 font-mono text-xs"
                value={JSON.stringify(value, null, 2)}
                onChange={(event) => {
                    try {
                        onChange(
                            JSON.parse(event.target.value) as Record<
                                string,
                                unknown
                            >,
                        );
                    } catch {
                        // Keep the last valid schema while the user types.
                    }
                }}
            />
        </div>
    );
}
