import { Link, router, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleDot,
    Clock3,
    ExternalLink,
    Filter,
    Play,
    TriangleAlert,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { show as showFlow } from '@/routes/flows';

type Period = '24h' | '7d' | '30d';
export type ExecutionFilter = 'failed' | 'stuck' | 'all';

export type FlowObservability = {
    period: Period;
    periodLabel: string;
    tiles: {
        started: number;
        completed: number;
        abandoned: number;
        failed: number;
        stuck: number;
        aiCostBrl: number;
    };
    daily: Array<{
        date: string;
        label: string;
        started: number;
        failed: number;
    }>;
    errors: Array<{
        code: string;
        label: string;
        nodeId: string;
        occurrences: number;
        share: number;
    }>;
    funnel: {
        nodes: Array<{
            nodeId: string;
            label: string;
            entered: number;
            exited: number;
            percentOfTotal: number;
            drop: number;
            dropPercent: number;
        }>;
        largestDrop: {
            label: string;
            drop: number;
            dropPercent: number;
        } | null;
        events: Array<{ name: string; count: number }>;
    };
};

type Execution = {
    id: string;
    contact: { name: string | null; phone: string } | null;
    status: string;
    statusLabel: string;
    nodeId: string | null;
    nodeLabel: string | null;
    reason: string | null;
    version: number;
    createdAt: string | null;
    traceUrl: string;
};

export type PaginatedExecutions = {
    data: Execution[];
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    counts: { failed: number; stuck: number; all: number };
};

type Analytics = FlowObservability;

type Props = {
    flowId: string;
    analytics: Analytics;
    executions: PaginatedExecutions;
    filters: { status: ExecutionFilter; error: string | null };
};

const periods: Array<{ value: Period; label: string }> = [
    { value: '24h', label: '24h' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
];

export function FlowExecutionsDashboard({
    flowId,
    analytics,
    executions,
    filters,
}: Props) {
    usePoll(3000, { only: ['observability', 'executions'] });

    const visit = (changes: Record<string, string | number | null>) => {
        router.get(
            showFlow.url(flowId, {
                query: {
                    tab: 'executions',
                    period: analytics.period,
                    execution_status: filters.status,
                    error: filters.error,
                    ...changes,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 overflow-y-auto px-5 pt-28 pb-10 lg:px-8">
            <section className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        Saúde do Fluxo
                    </p>
                    <h2 className="mt-1 text-xl font-semibold tracking-tight">
                        Execuções
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Atualizado automaticamente a cada 3 segundos ·{' '}
                        {analytics.periodLabel}
                    </p>
                </div>
                <div className="bg-background flex w-fit items-center gap-1 rounded-lg border p-1">
                    {periods.map((period) => (
                        <Button
                            key={period.value}
                            type="button"
                            size="sm"
                            variant={
                                analytics.period === period.value
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            onClick={() =>
                                visit({ period: period.value, page: 1 })
                            }
                        >
                            {period.label}
                        </Button>
                    ))}
                </div>
            </section>

            <section
                aria-label="Resumo das Execuções"
                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6"
            >
                <MetricTile
                    icon={Play}
                    label="Iniciadas"
                    value={analytics.tiles.started}
                />
                <MetricTile
                    icon={CheckCircle2}
                    label="Concluídas"
                    value={analytics.tiles.completed}
                    tone="positive"
                />
                <MetricTile
                    icon={CircleDot}
                    label="Abandonadas"
                    value={analytics.tiles.abandoned}
                />
                <MetricTile
                    icon={XCircle}
                    label="Com falha"
                    value={analytics.tiles.failed}
                    tone="negative"
                />
                <MetricTile
                    icon={Clock3}
                    label="Presas"
                    value={analytics.tiles.stuck}
                    tone="warning"
                />
                <MetricTile
                    icon={TriangleAlert}
                    label="Custo de IA"
                    value={formatCurrency(analytics.tiles.aiCostBrl)}
                    formatValue={false}
                />
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.55fr)]">
                <DailyExecutionsCard analytics={analytics} />
                <FrequentErrorsCard
                    errors={analytics.errors}
                    onFilter={(error) => visit({ error, page: 1 })}
                />
            </div>

            <FunnelCard funnel={analytics.funnel} />

            <ExecutionList
                executions={executions}
                filters={filters}
                onFilter={(status) =>
                    visit({ execution_status: status, error: null, page: 1 })
                }
                onPage={(page) => visit({ page })}
            />
        </div>
    );
}

function MetricTile({
    icon: Icon,
    label,
    value,
    tone,
    formatValue = true,
}: {
    icon: typeof Play;
    label: string;
    value: number | string;
    tone?: 'positive' | 'negative' | 'warning';
    formatValue?: boolean;
}) {
    const iconClass =
        tone === 'negative'
            ? 'text-destructive bg-destructive/10'
            : tone === 'warning'
              ? 'text-signal-attention bg-signal-attention/15'
              : tone === 'positive'
                ? 'text-signal-live bg-signal-live/15'
                : 'text-muted-foreground bg-muted';

    return (
        <Card className="min-h-28 shadow-none">
            <CardContent className="flex h-full flex-col justify-between gap-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground text-xs font-medium">
                        {label}
                    </span>
                    <span
                        className={`flex size-7 items-center justify-center rounded-md ${iconClass}`}
                    >
                        <Icon className="size-3.5" />
                    </span>
                </div>
                <strong className="text-2xl font-semibold tracking-tight tabular-nums">
                    {formatValue && typeof value === 'number'
                        ? value.toLocaleString('pt-BR')
                        : value}
                </strong>
            </CardContent>
        </Card>
    );
}

function DailyExecutionsCard({ analytics }: { analytics: Analytics }) {
    const maximum = Math.max(1, ...analytics.daily.map((day) => day.started));

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle className="text-base">
                        Execuções por dia
                    </CardTitle>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Inícios registrados no período.
                    </p>
                </div>
                <Badge variant="secondary">Série única</Badge>
            </CardHeader>
            <CardContent>
                {analytics.daily.length === 0 ? (
                    <EmptyState text="Ainda não há execuções neste período." />
                ) : (
                    <>
                        <div
                            className="flex h-44 items-end gap-2 border-b px-2 pb-1"
                            aria-label="Gráfico de execuções por dia"
                        >
                            {analytics.daily.map((day) => (
                                <div
                                    key={day.date}
                                    className="flex min-w-0 flex-1 flex-col items-center justify-end gap-2"
                                    title={`${day.label}: ${day.started} iniciadas, ${day.failed} com falha`}
                                >
                                    <span className="text-muted-foreground text-[10px] tabular-nums">
                                        {day.started}
                                    </span>
                                    <div
                                        className="bg-chart-2/80 hover:bg-chart-2 w-full min-w-2 rounded-t-sm transition-colors"
                                        style={{
                                            height: `${Math.max(4, (day.started / maximum) * 112)}px`,
                                        }}
                                    />
                                    <span className="text-muted-foreground text-[10px]">
                                        {day.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <details className="mt-4">
                            <summary className="text-muted-foreground hover:text-foreground flex min-h-10 cursor-pointer items-center gap-2 text-xs font-medium">
                                <Filter className="size-3.5" /> Ver tabela de
                                dados
                            </summary>
                            <div className="mt-2 overflow-x-auto rounded-md border">
                                <table className="w-full text-left text-xs">
                                    <thead className="bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2">Dia</th>
                                            <th className="px-3 py-2">
                                                Iniciadas
                                            </th>
                                            <th className="px-3 py-2">
                                                Falhas
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {analytics.daily.map((day) => (
                                            <tr
                                                key={day.date}
                                                className="border-t"
                                            >
                                                <td className="px-3 py-2">
                                                    {day.label}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums">
                                                    {day.started}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums">
                                                    {day.failed}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function FrequentErrorsCard({
    errors,
    onFilter,
}: {
    errors: Analytics['errors'];
    onFilter: (error: string) => void;
}) {
    const maximum = Math.max(1, ...errors.map((error) => error.occurrences));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Erros mais frequentes
                </CardTitle>
                <p className="text-muted-foreground mt-1 text-xs">
                    Causas agrupadas, não casos isolados.
                </p>
            </CardHeader>
            <CardContent className="grid gap-3">
                {errors.length === 0 ? (
                    <EmptyState text="Nenhuma falha agrupada no período." />
                ) : (
                    errors.map((error) => (
                        <button
                            key={`${error.code}-${error.nodeId}`}
                            type="button"
                            onClick={() => onFilter(error.code)}
                            className="group grid gap-1.5 text-left"
                        >
                            <div className="flex items-center justify-between gap-3 text-xs">
                                <span className="truncate font-medium">
                                    {error.label}
                                </span>
                                <span className="text-muted-foreground shrink-0 tabular-nums">
                                    {error.occurrences} · {error.share}%
                                </span>
                            </div>
                            <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                <div
                                    className="bg-destructive h-full rounded-full transition-[width]"
                                    style={{
                                        width: `${(error.occurrences / maximum) * 100}%`,
                                    }}
                                />
                            </div>
                            <span className="text-muted-foreground text-[11px] group-hover:underline">
                                Nó {error.nodeId} · ver Execuções
                            </span>
                        </button>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function FunnelCard({ funnel }: { funnel: Analytics['funnel'] }) {
    const maximum = Math.max(1, ...funnel.nodes.map((node) => node.entered));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Funil por Nó</CardTitle>
                <p className="text-muted-foreground mt-1 text-xs">
                    Chegadas e saídas por passo, na ordem do grafo. Ramificações
                    não são somadas como uma única linha.
                </p>
            </CardHeader>
            <CardContent className="grid gap-3">
                {funnel.nodes.length === 0 ? (
                    <EmptyState text="Publique o Fluxo para começar a formar o funil." />
                ) : (
                    funnel.nodes.map((node) => (
                        <div key={node.nodeId} className="grid gap-1.5">
                            <div className="flex flex-wrap items-baseline justify-between gap-2 text-xs">
                                <span className="font-medium">
                                    {node.label}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {node.entered} chegaram · {node.exited}{' '}
                                    passaram · {node.percentOfTotal}% do total
                                </span>
                            </div>
                            <div className="bg-muted h-7 overflow-hidden rounded-md">
                                <div
                                    className="bg-chart-1/80 flex h-full items-center rounded-md px-2 text-[11px] font-medium text-white transition-[width]"
                                    style={{
                                        width: `${Math.max(node.entered > 0 ? 7 : 0, (node.entered / maximum) * 100)}%`,
                                    }}
                                >
                                    {node.entered > 0 ? node.entered : ''}
                                </div>
                            </div>
                            {node.drop > 0 && (
                                <span className="text-muted-foreground flex items-center gap-1 text-[11px]">
                                    <ArrowDown className="size-3" /> queda de{' '}
                                    {node.drop} ({node.dropPercent}%)
                                </span>
                            )}
                        </div>
                    ))
                )}
                {funnel.events.length > 0 && (
                    <div className="border-border/70 mt-3 grid gap-2 rounded-lg border p-3">
                        <p className="text-xs font-semibold">
                            Eventos emitidos
                        </p>
                        {funnel.events.map((event) => (
                            <div
                                key={event.name}
                                className="flex items-center justify-between gap-3 text-xs"
                            >
                                <span className="text-muted-foreground">
                                    {event.name}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {event.count}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
                {funnel.largestDrop !== null && funnel.largestDrop.drop > 0 && (
                    <div className="border-signal-attention/30 bg-signal-attention/10 text-foreground flex items-start gap-2 rounded-md border px-3 py-2 text-xs">
                        <AlertTriangle className="text-signal-attention mt-0.5 size-4 shrink-0" />
                        <span>
                            Maior perda em{' '}
                            <strong>{funnel.largestDrop.label}</strong>:{' '}
                            {funnel.largestDrop.drop} execuções (
                            {funnel.largestDrop.dropPercent}%).
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ExecutionList({
    executions,
    filters,
    onFilter,
    onPage,
}: {
    executions: PaginatedExecutions;
    filters: { status: ExecutionFilter; error: string | null };
    onFilter: (status: ExecutionFilter) => void;
    onPage: (page: number) => void;
}) {
    return (
        <Card>
            <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle className="text-base">
                        Investigar execuções
                    </CardTitle>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Abra uma linha para chegar ao Nó exato.
                    </p>
                </div>
                <div className="bg-muted/50 flex w-fit items-center gap-1 rounded-md p-1">
                    {(['failed', 'stuck', 'all'] as ExecutionFilter[]).map(
                        (status) => (
                            <Button
                                key={status}
                                type="button"
                                size="sm"
                                variant={
                                    filters.status === status
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                onClick={() => onFilter(status)}
                            >
                                {status === 'failed'
                                    ? 'Com falha'
                                    : status === 'stuck'
                                      ? 'Presas'
                                      : 'Todas'}{' '}
                                <span className="text-muted-foreground tabular-nums">
                                    ({executions.counts[status]})
                                </span>
                            </Button>
                        ),
                    )}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {executions.data.length === 0 ? (
                    <div className="px-6 pb-6">
                        <EmptyState text="Nenhuma execução corresponde a este filtro." />
                    </div>
                ) : (
                    <div className="divide-border divide-y">
                        {executions.data.map((execution) => (
                            <Link
                                key={execution.id}
                                href={execution.traceUrl}
                                className="hover:bg-muted/30 focus-visible:bg-muted/30 flex items-center gap-4 px-6 py-4 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <span
                                    className={`flex size-8 shrink-0 items-center justify-center rounded-full ${execution.status === 'failed' ? 'bg-destructive/10 text-destructive' : execution.status === 'stuck' ? 'bg-signal-attention/15 text-signal-attention' : execution.status === 'completed' ? 'bg-signal-live/15 text-signal-live' : 'bg-muted text-muted-foreground'}`}
                                    aria-hidden="true"
                                >
                                    {execution.status === 'failed' ? (
                                        <XCircle className="size-4" />
                                    ) : execution.status === 'stuck' ? (
                                        <Clock3 className="size-4" />
                                    ) : execution.status === 'completed' ? (
                                        <CheckCircle2 className="size-4" />
                                    ) : (
                                        <CircleDot className="size-4" />
                                    )}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                        <span className="truncate">
                                            {execution.contact?.name ??
                                                execution.contact?.phone ??
                                                'Contato não identificado'}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="text-[10px]"
                                        >
                                            {execution.statusLabel}
                                        </Badge>
                                    </span>
                                    <span className="text-muted-foreground mt-1 block truncate text-xs">
                                        {execution.nodeLabel ??
                                            'Nó não identificado'}
                                        {execution.reason
                                            ? ` · ${execution.reason}`
                                            : ''}
                                    </span>
                                </span>
                                <span className="text-muted-foreground hidden shrink-0 text-right text-xs sm:block">
                                    <span className="block tabular-nums">
                                        v{execution.version}
                                    </span>
                                    <span>
                                        {formatDate(execution.createdAt)}
                                    </span>
                                </span>
                                <ExternalLink className="text-muted-foreground size-4 shrink-0" />
                            </Link>
                        ))}
                    </div>
                )}
                {executions.total > 0 && (
                    <div className="flex flex-col gap-3 border-t px-6 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-muted-foreground text-xs tabular-nums">
                            Mostrando {executions.from}–{executions.to} de{' '}
                            {executions.total}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Página anterior"
                                disabled={executions.currentPage <= 1}
                                onClick={() =>
                                    onPage(executions.currentPage - 1)
                                }
                            >
                                <ChevronLeft />
                            </Button>
                            <span className="text-muted-foreground text-xs tabular-nums">
                                {executions.currentPage} / {executions.lastPage}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Próxima página"
                                disabled={
                                    executions.currentPage >=
                                    executions.lastPage
                                }
                                onClick={() =>
                                    onPage(executions.currentPage + 1)
                                }
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="text-muted-foreground rounded-md border border-dashed px-4 py-8 text-center text-xs">
            {text}
        </div>
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 2,
    }).format(value);
}

function formatDate(value: string | null): string {
    return value === null
        ? '—'
        : new Intl.DateTimeFormat('pt-BR', {
              day: '2-digit',
              month: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value));
}
