import { Link, usePoll } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleDot,
    Clock3,
    Filter,
    Play,
    TriangleAlert,
    XCircle,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Period = '24h' | '7d' | '30d';

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
    analytics: Analytics;
    tracesUrl: string | null;
};

export function FlowExecutionsDashboard({ analytics, tracesUrl }: Props) {
    usePoll(3000, { only: ['observability'] });

    return (
        <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5">
            <section
                aria-label="Resumo das Execuções"
                className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"
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

            <div className="flex flex-col gap-4 lg:flex-row">
                <DailyExecutionsCard analytics={analytics} />
                <FrequentErrorsCard
                    errors={analytics.errors}
                    tracesUrl={tracesUrl}
                />
            </div>

            <FunnelCard funnel={analytics.funnel} />
        </div>
    );
}

function Panel({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'border-border/70 rounded-[11px] border',
                className,
            )}
        >
            {children}
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
        <Panel className="p-3.5">
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground text-[11px] font-semibold">
                    {label}
                </span>
                <span
                    className={`flex size-6 items-center justify-center rounded-md ${iconClass}`}
                >
                    <Icon className="size-3" />
                </span>
            </div>
            <strong className="mt-0.5 block text-[25px] leading-none font-semibold tracking-[-0.015em] tabular-nums">
                {formatValue && typeof value === 'number'
                    ? value.toLocaleString('pt-BR')
                    : value}
            </strong>
        </Panel>
    );
}

function DailyExecutionsCard({ analytics }: { analytics: Analytics }) {
    const width = 620;
    const height = 190;
    const paddingLeft = 32;
    const paddingBottom = 20;
    const days = analytics.daily;
    const maximum = Math.max(1, ...days.map((day) => day.started));
    const innerWidth = width - paddingLeft;
    const step = days.length > 1 ? innerWidth / (days.length - 1) : 0;
    const yFor = (started: number) =>
        height - paddingBottom - (started / maximum) * (height - paddingBottom - 10);
    const points = days.map((day, index) => ({
        x: paddingLeft + step * index,
        y: yFor(day.started),
        day,
    }));
    const linePath = points
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x} ${point.y}`)
        .join(' ');
    const areaPath =
        points.length > 0
            ? `${linePath} L${points[points.length - 1].x} ${height - paddingBottom} L${points[0].x} ${height - paddingBottom} Z`
            : '';

    return (
        <Panel className="min-w-0 flex-1 p-4">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-[13.5px] font-semibold">
                    Execuções por dia
                </h2>
                <span className="text-muted-foreground text-[11px]">
                    {analytics.periodLabel}
                </span>
            </div>
            {days.length === 0 ? (
                <EmptyState text="Ainda não há execuções neste período." />
            ) : (
                <>
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        className="mt-2 block w-full"
                        role="img"
                        aria-label={`Execuções por dia: ${days.map((day) => `${day.label} ${day.started}`).join(', ')}`}
                    >
                        {[0, 0.33, 0.66, 1].map((fraction) => (
                            <line
                                key={fraction}
                                x1={paddingLeft}
                                x2={width}
                                y1={
                                    height -
                                    paddingBottom -
                                    fraction * (height - paddingBottom - 10)
                                }
                                y2={
                                    height -
                                    paddingBottom -
                                    fraction * (height - paddingBottom - 10)
                                }
                                stroke="var(--border)"
                                strokeWidth={1}
                                opacity={fraction === 0 ? 1 : 0.5}
                            />
                        ))}
                        {areaPath !== '' && (
                            <path
                                d={areaPath}
                                fill="var(--signal-live)"
                                opacity={0.09}
                            />
                        )}
                        <path
                            d={linePath}
                            fill="none"
                            stroke="var(--signal-live)"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                        {points.map((point) => (
                            <circle
                                key={point.day.date}
                                cx={point.x}
                                cy={point.y}
                                r={3.5}
                                fill="var(--signal-live)"
                                stroke="var(--background)"
                                strokeWidth={1.5}
                            />
                        ))}
                        {points.map((point) => (
                            <text
                                key={`${point.day.date}-label`}
                                x={point.x}
                                y={height - 4}
                                fontSize={9.5}
                                textAnchor="middle"
                                fill="var(--muted-foreground)"
                            >
                                {point.day.label}
                            </text>
                        ))}
                    </svg>
                    <details className="mt-2">
                        <summary className="text-muted-foreground hover:text-foreground flex min-h-8 cursor-pointer items-center gap-2 text-xs font-medium">
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
                                        <th className="px-3 py-2">Falhas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {days.map((day) => (
                                        <tr key={day.date} className="border-t">
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
        </Panel>
    );
}

function FrequentErrorsCard({
    errors,
    tracesUrl,
}: {
    errors: Analytics['errors'];
    tracesUrl: string | null;
}) {
    const maximum = Math.max(1, ...errors.map((error) => error.occurrences));

    return (
        <Panel className="w-full p-4 lg:w-[370px] lg:shrink-0">
            <h2 className="text-[13.5px] font-semibold">
                Erros mais frequentes
            </h2>
            <div className="mt-3.5 grid gap-3.5">
                {errors.length === 0 ? (
                    <EmptyState text="Nenhuma falha agrupada no período." />
                ) : (
                    errors.map((error) => {
                        const bar = (
                            <>
                                <div className="flex items-center justify-between gap-3 text-xs">
                                    <span className="truncate font-medium">
                                        {error.label}
                                    </span>
                                    <span className="shrink-0 font-semibold tabular-nums">
                                        {error.occurrences}
                                    </span>
                                </div>
                                <div className="bg-muted h-2 overflow-hidden rounded-full">
                                    <div
                                        className="bg-destructive h-full rounded-full transition-[width]"
                                        style={{
                                            width: `${(error.occurrences / maximum) * 100}%`,
                                        }}
                                    />
                                </div>
                            </>
                        );
                        const key = `${error.code}-${error.nodeId}`;

                        return tracesUrl === null ? (
                            <div key={key} className="grid gap-1.5">
                                {bar}
                            </div>
                        ) : (
                            <Link
                                key={key}
                                href={tracesUrl}
                                className="group grid gap-1.5 text-left"
                            >
                                {bar}
                            </Link>
                        );
                    })
                )}
            </div>
        </Panel>
    );
}

function FunnelCard({ funnel }: { funnel: Analytics['funnel'] }) {
    const maximum = Math.max(1, ...funnel.nodes.map((node) => node.entered));

    return (
        <Panel className="flex min-h-0 flex-1 flex-col p-4">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-[13.5px] font-semibold">
                    Onde as execuções param
                </h2>
                <span className="text-muted-foreground text-[11px]">
                    de {funnel.nodes[0]?.entered.toLocaleString('pt-BR') ?? 0}{' '}
                    que começaram
                </span>
            </div>
            <div className="mt-3 grid gap-1.5">
                {funnel.nodes.length === 0 ? (
                    <EmptyState text="Publique o Fluxo para começar a formar o funil." />
                ) : (
                    funnel.nodes.map((node, index) => (
                        <div
                            key={node.nodeId}
                            className="flex items-center gap-3"
                        >
                            <span className="w-44 shrink-0 truncate text-xs font-medium">
                                {node.label}
                            </span>
                            <span className="relative h-[22px] min-w-0 flex-1">
                                <span
                                    className={cn(
                                        'absolute top-0 left-0 h-[22px] rounded-r-[5px]',
                                        node.label ===
                                            funnel.largestDrop?.label
                                            ? 'bg-signal-attention'
                                            : 'bg-signal-live',
                                    )}
                                    style={{
                                        width: `${Math.max(node.entered > 0 ? 2 : 0, (node.entered / maximum) * 100)}%`,
                                    }}
                                />
                            </span>
                            <span className="w-14 shrink-0 text-right text-xs font-semibold tabular-nums">
                                {node.entered.toLocaleString('pt-BR')}
                            </span>
                            <span className="text-muted-foreground w-11 shrink-0 text-right text-[11.5px] tabular-nums">
                                {node.percentOfTotal}%
                            </span>
                            <span
                                className={cn(
                                    'w-28 shrink-0 text-right text-[11.5px] font-semibold',
                                    node.drop > 0
                                        ? 'text-signal-attention'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {node.drop > 0
                                    ? `−${node.dropPercent}%`
                                    : index === 0
                                      ? ''
                                      : '0%'}
                            </span>
                        </div>
                    ))
                )}
            </div>
            {funnel.events.length > 0 && (
                <div className="border-border/70 mt-3 grid gap-2 rounded-lg border p-3">
                    <p className="text-xs font-semibold">Eventos emitidos</p>
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
                <div className="border-signal-attention/30 bg-signal-attention/10 text-foreground mt-3 flex items-start gap-2 rounded-md border px-3 py-2 text-xs">
                    <AlertTriangle className="text-signal-attention mt-0.5 size-4 shrink-0" />
                    <span>
                        A maior perda é em{' '}
                        <strong>{funnel.largestDrop.label}</strong>:{' '}
                        {funnel.largestDrop.drop} execuções (
                        {funnel.largestDrop.dropPercent}%).
                    </span>
                </div>
            )}
        </Panel>
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
