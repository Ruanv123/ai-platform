import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Copy,
    MoreHorizontal,
    Plus,
    Search,
    Trash2,
    Workflow,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { cn } from '@/lib/utils';
import { postJson } from '@/lib/flow-http';
import { destroy, index, pause, resume, show, store } from '@/routes/flows';
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
    executionCount: number;
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
        search: string;
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
    const [flowToDelete, setFlowToDelete] = useState<Flow | null>(null);
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<typeof filters>) => {
        const query = {
            ...filters,
            ...changes,
            page: 1,
            per_page: flows.perPage,
        };
        router.get(
            index.url({ query }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        applyFilters({ search: search || '' });
    };

    const changePage = (page: number) => {
        router.get(
            index.url({
                query: { ...filters, page, per_page: flows.perPage },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const changePerPage = (perPage: number) => {
        router.get(
            index.url({ query: { ...filters, page: 1, per_page: perPage } }),
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
            <div className="flex flex-1 flex-col gap-0 px-8 pt-7 pb-8">
                <div className="flex items-center justify-between gap-6">
                    <h1 className="text-2xl font-semibold tracking-[-0.01em]">
                        Fluxos
                    </h1>
                    <div className="flex shrink-0 gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setTemplatesOpen(true)}
                        >
                            <Copy /> Usar modelo
                        </Button>
                        {permissions.canManageFlows && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus /> Novo fluxo
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 pt-5 pb-4">
                    <form
                        onSubmit={submitSearch}
                        className="relative w-[280px]"
                    >
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar fluxo…"
                            className="bg-background h-[34px] pl-8 text-[12.5px]"
                            aria-label="Buscar fluxo"
                        />
                    </form>
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
                        label="Publicação"
                        value={filters.status ?? ''}
                        onChange={(value) =>
                            applyFilters({ status: value || null })
                        }
                        options={[
                            { value: '', label: 'Todos os status' },
                            ...statuses,
                        ]}
                    />
                    <span className="flex-1" />
                    <span className="text-muted-foreground text-xs">
                        {flows.total} {flows.total === 1 ? 'fluxo' : 'fluxos'}
                    </span>
                </div>

                {flows.data.length === 0 ? (
                    <div className="border-border/70 flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-12 text-center">
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
                    <div className="border-border/70 overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="h-9 w-36 text-[11px]">
                                        Status
                                    </TableHead>
                                    <TableHead className="h-9 min-w-60 text-[11px]">
                                        Fluxo
                                    </TableHead>
                                    <TableHead className="h-9 text-[11px]">
                                        Tipo
                                    </TableHead>
                                    <TableHead className="h-9 text-[11px]">
                                        Gatilho
                                    </TableHead>
                                    <TableHead className="h-9 text-[11px]">
                                        Canal
                                    </TableHead>
                                    <TableHead className="h-9 text-right text-[11px]">
                                        Execuções 7 d
                                    </TableHead>
                                    <TableHead className="h-9 text-right text-[11px]">
                                        Conclusão
                                    </TableHead>
                                    <TableHead className="h-9 text-right text-[11px]">
                                        Última execução
                                    </TableHead>
                                    {permissions.canManageFlows && (
                                        <TableHead className="h-9 w-12">
                                            <span className="sr-only">
                                                Ações
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {flows.data.map((flow) => (
                                    <TableRow key={flow.id} className="group">
                                        <TableCell
                                            className="py-3"
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
                                                <span
                                                    className={cn(
                                                        'text-[12.5px] font-medium',
                                                        flow.status ===
                                                            'paused' &&
                                                            'text-signal-attention',
                                                    )}
                                                >
                                                    {flow.statusLabel}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="py-3">
                                            <Link
                                                href={show(flow.id).url}
                                                className="group/link flex items-center gap-3"
                                                prefetch
                                            >
                                                <span className="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                                                    <Workflow className="size-4" />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="flex items-center gap-2">
                                                        <strong className="block truncate text-[13.5px] font-semibold group-hover/link:underline">
                                                            {flow.name}
                                                        </strong>
                                                        {flow.hasUnpublishedChanges && (
                                                            <span className="text-signal-attention bg-signal-attention/15 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                                                                alterações não
                                                                publicadas
                                                            </span>
                                                        )}
                                                    </span>
                                                </span>
                                            </Link>
                                        </TableCell>
                                        <TableCell className="py-3">
                                            <TypeChip
                                                label={
                                                    flow.categoryLabel ?? '—'
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="py-3">
                                            <span className="bg-muted rounded-md px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap">
                                                {triggerLabel(
                                                    flow,
                                                    triggerTypes,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground py-3 text-[12.5px] whitespace-nowrap">
                                            {flow.channelLabel ?? '—'}
                                        </TableCell>
                                        <TableCell className="py-3 text-right text-[13px] font-semibold tabular-nums">
                                            {flow.executionsLast7Days}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'py-3 text-right text-[13px] font-semibold tabular-nums',
                                                flow.completionRate !==
                                                    null &&
                                                    flow.completionRate < 70 &&
                                                    'text-signal-attention',
                                            )}
                                        >
                                            {flow.completionRate === null
                                                ? '—'
                                                : `${flow.completionRate}%`}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground py-3 text-right text-[12.5px] whitespace-nowrap">
                                            {flow.lastExecutionAt === null
                                                ? 'Nunca'
                                                : formatDate(
                                                      flow.lastExecutionAt,
                                                  )}
                                        </TableCell>
                                        {permissions.canManageFlows && (
                                            <TableCell className="py-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Ações para ${flow.name}`}
                                                        >
                                                            <MoreHorizontal />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                setFlowToDelete(
                                                                    flow,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Excluir
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <FlowPagination
                            flows={flows}
                            onPageChange={changePage}
                            onPerPageChange={changePerPage}
                        />
                    </div>
                )}
            </div>

            <DeleteFlowDialog
                key={flowToDelete?.id ?? 'no-flow'}
                flow={flowToDelete}
                open={flowToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setFlowToDelete(null);
                    }
                }}
            />
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
        <div className="border-border/70 bg-background relative flex h-[34px] items-center rounded-lg border pr-7 pl-3 text-[12.5px] font-medium">
            <span className="sr-only">{label}</span>
            <select
                aria-label={label}
                className="absolute inset-0 h-full w-full cursor-pointer appearance-none bg-transparent pr-7 pl-3 text-[12.5px] font-medium outline-none"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <span aria-hidden="true">
                {options.find((option) => option.value === value)?.label ??
                    label}
            </span>
            <ChevronDown className="text-muted-foreground pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2" />
        </div>
    );
}

function TypeChip({ label }: { label: string }) {
    const isConversation = label === 'Conversa';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap',
                isConversation
                    ? 'bg-signal-live/15 text-signal-live'
                    : 'bg-muted text-muted-foreground',
            )}
        >
            <span
                className={cn(
                    'size-1.5 rounded-full',
                    isConversation ? 'bg-signal-live' : 'bg-border',
                )}
            />
            {label}
        </span>
    );
}

function FlowPagination({
    flows,
    onPageChange,
    onPerPageChange,
}: {
    flows: Props['flows'];
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
}) {
    const hasPreviousPage = flows.currentPage > 1;
    const hasNextPage = flows.currentPage < flows.lastPage;

    return (
        <div className="flex flex-wrap items-center gap-3.5 border-t px-4 py-3">
            <span className="text-muted-foreground text-xs">
                Mostrando{' '}
                <strong className="text-foreground font-semibold">
                    {flows.from ?? 0}–{flows.to ?? 0}
                </strong>{' '}
                de{' '}
                <strong className="text-foreground font-semibold">
                    {flows.total}
                </strong>
            </span>

            <span className="flex items-center gap-1.5">
                <label
                    htmlFor="flows-por-pagina"
                    className="text-muted-foreground text-xs"
                >
                    Por página
                </label>
                <select
                    id="flows-por-pagina"
                    value={flows.perPage}
                    onChange={(event) =>
                        onPerPageChange(Number(event.target.value))
                    }
                    className="border-border/70 bg-background h-[30px] rounded-md border px-2 text-xs"
                >
                    {[10, 25, 50].map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>
            </span>

            <span className="flex-1" />

            <nav
                aria-label="Paginação"
                className="flex items-center gap-1"
            >
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-[30px]"
                    onClick={() => onPageChange(flows.currentPage - 1)}
                    disabled={!hasPreviousPage}
                    aria-label="Página anterior"
                >
                    <ChevronLeft />
                </Button>
                {paginationRange(flows.currentPage, flows.lastPage).map(
                    (page, index) =>
                        page === 'ellipsis' ? (
                            <span
                                key={`ellipsis-${index}`}
                                className="text-muted-foreground px-1 text-xs"
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={page}
                                type="button"
                                variant={
                                    page === flows.currentPage
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                className="h-[30px] min-w-[30px] px-2"
                                aria-current={
                                    page === flows.currentPage
                                        ? 'page'
                                        : undefined
                                }
                                onClick={() => onPageChange(page)}
                            >
                                {page}
                            </Button>
                        ),
                )}
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-[30px]"
                    onClick={() => onPageChange(flows.currentPage + 1)}
                    disabled={!hasNextPage}
                    aria-label="Próxima página"
                >
                    <ChevronRight />
                </Button>
            </nav>
        </div>
    );
}

function paginationRange(
    currentPage: number,
    lastPage: number,
): Array<number | 'ellipsis'> {
    const pages = new Set<number>([1, lastPage]);
    for (let page = currentPage - 1; page <= currentPage + 1; page += 1) {
        if (page >= 1 && page <= lastPage) {
            pages.add(page);
        }
    }

    const sorted = Array.from(pages).sort((a, b) => a - b);
    const withEllipsis: Array<number | 'ellipsis'> = [];
    sorted.forEach((page, index) => {
        if (index > 0 && page - sorted[index - 1] > 1) {
            withEllipsis.push('ellipsis');
        }
        withEllipsis.push(page);
    });

    return withEllipsis;
}

function DeleteFlowDialog({
    flow,
    open,
    onOpenChange,
}: {
    flow: Flow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (flow === null) {
        return null;
    }

    const hasExecutionHistory = flow.executionCount > 0;
    const handleDelete = () => {
        if (hasExecutionHistory || processing) {
            return;
        }

        setProcessing(true);
        setError(null);
        router.delete(destroy(flow.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (errors) =>
                setError(
                    typeof errors.flow === 'string'
                        ? errors.flow
                        : 'Não foi possível excluir o Fluxo.',
                ),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir Fluxo?</DialogTitle>
                    <DialogDescription>
                        {hasExecutionHistory ? (
                            <>
                                O Fluxo <strong>{flow.name}</strong> possui{' '}
                                {flow.executionCount}{' '}
                                {flow.executionCount === 1
                                    ? 'Execução registrada'
                                    : 'Execuções registradas'}{' '}
                                e não pode ser excluído porque esse histórico
                                precisa ser preservado.
                            </>
                        ) : (
                            <>
                                O Fluxo <strong>{flow.name}</strong> e suas
                                Versões serão excluídos permanentemente. Essa
                                ação não pode ser desfeita.
                            </>
                        )}
                    </DialogDescription>
                    {error !== null && (
                        <p className="text-destructive text-sm">{error}</p>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            {hasExecutionHistory ? 'Fechar' : 'Cancelar'}
                        </Button>
                    </DialogClose>
                    {!hasExecutionHistory && (
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={handleDelete}
                        >
                            {processing ? 'Excluindo...' : 'Excluir Fluxo'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
