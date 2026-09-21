import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    Filter,
    Plus,
    Workflow,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { postJson } from '@/lib/flow-http';
import { index, pause, resume, show, store } from '@/routes/flows';
import { clone } from '@/routes/flows/templates';
import type { TeamPermissions } from '@/types';

type Flow = {
    id: string;
    name: string;
    status: 'draft' | 'published' | 'paused';
    statusLabel: string;
    hasUnpublishedChanges: boolean;
    channelLabel: string | null;
    triggerType: string;
    triggerEvent: string | null;
    categoryLabel: string | null;
    executionsLast7Days: number;
    completionRate: number | null;
    lastExecutionAt: string | null;
};

type Props = {
    flows: {
        data: Flow[];
        currentPage: number;
        lastPage: number;
        from: number | null;
        to: number | null;
        total: number;
        perPage: number;
    };
    filters: {
        channel: string | null;
        trigger: string | null;
        status: string | null;
    };
    channels: Array<{ id: string; label: string; type: string }>;
    triggerTypes: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    templates: Array<{
        id: string;
        name: string;
        description: string;
        category: string;
        hasRequiredChannel: boolean;
    }>;
    permissions: TeamPermissions;
};

export default function FlowsIndex({
    flows,
    filters,
    channels,
    triggerTypes,
    statuses,
    templates,
    permissions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [templatesOpen, setTemplatesOpen] = useState(false);
    const [cloning, setCloning] = useState<string | null>(null);

    const applyFilters = (changes: Partial<typeof filters>) => {
        const query = { ...filters, ...changes, page: 1 };
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const cloneTemplate = async (templateId: string) => {
        setCloning(templateId);
        try {
            const cloned = await postJson<{ id: string }>(
                clone(templateId).url,
                {},
            );
            router.visit(show(cloned.id).url);
        } finally {
            setCloning(null);
        }
    };

    return (
        <>
            <Head title="Fluxos" />
            <div className="flex flex-1 flex-col gap-7 p-4 md:p-8">
                <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <Heading
                        title="Fluxos"
                        description="Desenhe o atendimento da sua Empresa, teste o rascunho e publique quando estiver pronto."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setTemplatesOpen(true)}
                        >
                            <Copy /> Usar modelo
                        </Button>
                        {permissions.canManageFlows && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus /> Novo Fluxo
                            </Button>
                        )}
                    </div>
                </div>

                <div className="bg-card/60 flex flex-col gap-3 rounded-xl border p-3 lg:flex-row lg:items-center">
                    <div className="text-muted-foreground flex items-center gap-2 px-2 text-sm">
                        <Filter className="size-4" /> Filtros
                    </div>
                    <FilterSelect
                        label="Canal"
                        value={filters.channel ?? ''}
                        onChange={(value) =>
                            applyFilters({ channel: value || null })
                        }
                        options={[
                            { value: '', label: 'Todos os canais' },
                            ...channels.map((channel) => ({
                                value: channel.id,
                                label: channel.label,
                            })),
                        ]}
                    />
                    <FilterSelect
                        label="Gatilho"
                        value={filters.trigger ?? ''}
                        onChange={(value) =>
                            applyFilters({ trigger: value || null })
                        }
                        options={[
                            { value: '', label: 'Todos os gatilhos' },
                            ...triggerTypes,
                        ]}
                    />
                    <FilterSelect
                        label="Status"
                        value={filters.status ?? ''}
                        onChange={(value) =>
                            applyFilters({ status: value || null })
                        }
                        options={[
                            { value: '', label: 'Todos os status' },
                            ...statuses,
                        ]}
                    />
                    <div className="text-muted-foreground ml-auto px-2 text-xs tabular-nums">
                        {flows.total} {flows.total === 1 ? 'Fluxo' : 'Fluxos'}
                    </div>
                </div>

                {flows.data.length === 0 ? (
                    <div className="border-border/70 flex flex-1 flex-col items-center justify-center gap-4 rounded-2xl border border-dashed p-12 text-center">
                        <span className="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-2xl">
                            <Workflow className="size-6" />
                        </span>
                        <div className="grid gap-1">
                            <h2 className="font-semibold tracking-tight">
                                Nenhum Fluxo neste filtro
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-6">
                                Comece com um desenho em branco, use um modelo
                                curado ou descreva o atendimento para a IA.
                            </p>
                        </div>
                        {permissions.canManageFlows && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus /> Criar primeiro Fluxo
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="border-border/70 bg-card overflow-hidden rounded-2xl border shadow-xs">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-36">
                                        Status
                                    </TableHead>
                                    <TableHead className="min-w-60">
                                        Fluxo
                                    </TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Gatilho</TableHead>
                                    <TableHead>Canal</TableHead>
                                    <TableHead className="text-right">
                                        7 dias
                                    </TableHead>
                                    <TableHead>Conclusão</TableHead>
                                    <TableHead>Última execução</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {flows.data.map((flow) => (
                                    <TableRow key={flow.id} className="group">
                                        <TableCell
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={
                                                        flow.status ===
                                                        'published'
                                                    }
                                                    aria-label={`${flow.status === 'published' ? 'Pausar' : 'Ativar'} ${flow.name}`}
                                                    disabled={
                                                        !permissions.canManageFlows ||
                                                        flow.status === 'draft'
                                                    }
                                                    className={`relative h-5 w-9 rounded-full transition-colors ${flow.status === 'published' ? 'bg-signal-live' : 'bg-muted'} disabled:cursor-not-allowed disabled:opacity-50`}
                                                    onClick={() =>
                                                        router.post(
                                                            (flow.status ===
                                                            'published'
                                                                ? pause(flow.id)
                                                                : resume(
                                                                      flow.id,
                                                                  )
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <span
                                                        className={`absolute top-0.5 size-4 rounded-full bg-white shadow-sm transition-transform ${flow.status === 'published' ? 'translate-x-4' : 'translate-x-0.5'}`}
                                                    />
                                                </button>
                                                <span className="text-xs">
                                                    {flow.statusLabel}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={show(flow.id).url}
                                                className="group/link flex items-center gap-3"
                                                prefetch
                                            >
                                                <span className="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                                                    <Workflow className="size-4" />
                                                </span>
                                                <span className="min-w-0">
                                                    <strong className="block truncate font-medium group-hover/link:underline">
                                                        {flow.name}
                                                    </strong>
                                                    {flow.hasUnpublishedChanges && (
                                                        <span className="text-signal-attention text-xs">
                                                            Alterações não
                                                            publicadas
                                                        </span>
                                                    )}
                                                </span>
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {flow.categoryLabel ?? '—'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {triggerLabel(flow, triggerTypes)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {flow.channelLabel ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {flow.executionsLast7Days}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {flow.completionRate === null
                                                ? '—'
                                                : `${flow.completionRate}%`}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">
                                            {flow.lastExecutionAt === null
                                                ? 'Nunca'
                                                : formatDate(
                                                      flow.lastExecutionAt,
                                                  )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {flows.total > 0 && (
                    <div className="text-muted-foreground flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span className="tabular-nums">
                            Mostrando {flows.from}–{flows.to} de {flows.total}
                        </span>
                        <div className="flex items-center gap-1">
                            <PaginationButton
                                disabled={flows.currentPage <= 1}
                                href={index.url({
                                    query: {
                                        ...filters,
                                        page: flows.currentPage - 1,
                                    },
                                })}
                            >
                                <ChevronLeft />
                            </PaginationButton>
                            <span className="px-3 text-xs tabular-nums">
                                Página {flows.currentPage} de {flows.lastPage}
                            </span>
                            <PaginationButton
                                disabled={flows.currentPage >= flows.lastPage}
                                href={index.url({
                                    query: {
                                        ...filters,
                                        page: flows.currentPage + 1,
                                    },
                                })}
                            >
                                <ChevronRight />
                            </PaginationButton>
                        </div>
                    </div>
                )}
            </div>

            <CreateFlowDialog open={createOpen} onOpenChange={setCreateOpen} />
            <TemplateDialog
                open={templatesOpen}
                onOpenChange={setTemplatesOpen}
                templates={templates}
                cloning={cloning}
                onClone={(id) => void cloneTemplate(id)}
                canManage={permissions.canManageFlows}
            />
        </>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex items-center">
            <span className="sr-only">{label}</span>
            <select
                aria-label={label}
                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

function PaginationButton({
    href,
    disabled,
    children,
}: {
    href: string;
    disabled: boolean;
    children: React.ReactNode;
}) {
    return (
        <Button
            variant="outline"
            size="icon"
            disabled={disabled}
            asChild={!disabled}
        >
            {disabled ? (
                <span>{children}</span>
            ) : (
                <Link href={href} preserveScroll preserveState>
                    {children}
                </Link>
            )}
        </Button>
    );
}

function CreateFlowDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form {...store.form()} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Novo Fluxo</DialogTitle>
                        <DialogDescription>
                            Comece com um nome. O desenho abre vazio para você
                            escolher o gatilho.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="flow-name">Nome do Fluxo</Label>
                        <Input
                            id="flow-name"
                            name="name"
                            placeholder="Ex.: Agendamento pelo WhatsApp"
                            required
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit">Abrir editor</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function TemplateDialog({
    open,
    onOpenChange,
    templates,
    cloning,
    onClone,
    canManage,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    templates: Props['templates'];
    cloning: string | null;
    onClone: (id: string) => void;
    canManage: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Modelos de Fluxo</DialogTitle>
                    <DialogDescription>
                        Um ponto de partida curado para adaptar ao seu
                        atendimento.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2 sm:grid-cols-3">
                    {templates.map((template) => (
                        <button
                            type="button"
                            key={template.id}
                            disabled={!canManage || cloning !== null}
                            onClick={() => onClone(template.id)}
                            className="hover:bg-muted/50 border-border/70 rounded-xl border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="bg-muted text-muted-foreground flex size-8 items-center justify-center rounded-lg">
                                    <Copy className="size-4" />
                                </span>
                                <Badge variant="outline">
                                    {template.category}
                                </Badge>
                            </div>
                            <strong className="mt-4 block text-sm">
                                {template.name}
                            </strong>
                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                {template.description}
                            </p>
                            <span className="text-muted-foreground mt-4 block text-[11px]">
                                {template.hasRequiredChannel
                                    ? 'Canal disponível'
                                    : 'Configure um Canal depois'}
                            </span>
                        </button>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function triggerLabel(
    flow: Flow,
    triggerTypes: Array<{ value: string; label: string }>,
): string {
    if (flow.triggerType === 'webhook' && flow.triggerEvent) {
        return `Webhook · ${flow.triggerEvent}`;
    }

    return (
        triggerTypes.find((trigger) => trigger.value === flow.triggerType)
            ?.label ?? flow.triggerType
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}
