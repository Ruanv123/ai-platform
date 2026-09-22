import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock3, Radio, Sparkles, UserRound } from 'lucide-react';
import type { PaginatedExecutions } from '@/components/flows/flow-executions-dashboard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show as showFlow } from '@/routes/flows';
import { show as showExecution } from '@/routes/flows/executions';

type ExecutionStatus =
    | 'running'
    | 'waiting'
    | 'paused'
    | 'completed'
    | 'failed'
    | 'abandoned'
    | 'stuck';

type FollowedEdge = {
    id: string;
    source: string | null;
    target: string | null;
    label: string | null;
};

type TraceStep = {
    id: string;
    sequence: number;
    nodeId: string;
    nodeName: string;
    nodeType: string;
    nodeTypeLabel: string;
    result: 'running' | 'waiting' | 'failed' | 'completed';
    resultLabel: string;
    durationMs: number;
    error: string | null;
    variablesSnapshot: Record<string, unknown>;
    startedAt: string;
    followedEdge: FollowedEdge | null;
    highlighted: boolean;
    httpRequest?: Record<string, unknown> | null;
    httpResponse?: Record<string, unknown> | null;
    httpStatus?: number | null;
    retryCount?: number;
    aiPrompt?: string | null;
    aiResponse?: string | null;
    aiInputTokens?: number | null;
    aiOutputTokens?: number | null;
    aiCostBrl?: number | null;
    aiToolCalls?: Array<Record<string, unknown>> | null;
};

type UnreachedNode = {
    nodeId: string;
    nodeName: string;
    nodeType: string;
    nodeTypeLabel: string;
    reached: false;
};

type SiblingStatus = 'failed' | 'stuck' | 'all';

type Props = {
    execution: {
        id: string;
        flowId: string;
        flowName: string;
        contact: { name: string | null; phone: string } | null;
        channel: { label: string; type: string } | null;
        version: number;
        status: ExecutionStatus;
        statusLabel: string;
        finalReason: string | null;
        durationMs: number;
        startedAt: string;
        finishedAt: string | null;
        aiCostBrl?: number;
    };
    canViewSensitiveTrace: boolean;
    siblingExecutions: PaginatedExecutions;
    siblingStatus: SiblingStatus;
    steps: TraceStep[];
    unreachedNodes: UnreachedNode[];
};

export default function FlowExecutionShow({
    execution,
    siblingExecutions,
    siblingStatus,
    steps,
    unreachedNodes,
}: Props) {
    const failed = execution.status === 'failed';

    const visitSiblingStatus = (status: SiblingStatus) => {
        router.get(
            showExecution.url([execution.flowId, execution.id], {
                query: { execution_status: status },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`Traço · ${execution.flowName}`} />
            <div className="flex h-full min-h-0 flex-1 flex-col">
                <header className="border-border/70 bg-background flex h-14 shrink-0 items-center gap-3 border-b px-5">
                    <Button
                        variant="outline"
                        size="icon"
                        asChild
                        aria-label="Voltar para o dashboard do Fluxo"
                        className="size-[30px]"
                    >
                        <Link
                            href={showFlow.url(execution.flowId, {
                                query: { tab: 'executions' },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <span className="text-sm font-semibold">
                        {execution.flowName}
                    </span>
                    <span className="text-muted-foreground text-[12.5px]">
                        · Execuções
                    </span>
                    <span className="flex-1" />
                    <div className="bg-muted flex items-center gap-0.5 rounded-lg p-1">
                        <Link
                            href={showFlow.url(execution.flowId, {
                                query: { tab: 'design' },
                            })}
                            className="text-muted-foreground rounded-md px-3 py-1 text-[12.5px] font-medium"
                        >
                            Desenho
                        </Link>
                        <Link
                            href={showFlow.url(execution.flowId, {
                                query: { tab: 'executions' },
                            })}
                            className="bg-background rounded-md px-3 py-1 text-[12.5px] font-semibold shadow-sm"
                        >
                            Execuções
                        </Link>
                    </div>
                    <span className="flex-1" />
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={showFlow.url(execution.flowId, {
                                query: { tab: 'executions' },
                            })}
                        >
                            Ver métricas
                        </Link>
                    </Button>
                </header>

                <div className="flex min-h-0 flex-1">
                    <aside className="border-border/70 flex w-[370px] shrink-0 flex-col border-r">
                        <div className="border-border/70 flex items-center gap-1 border-b px-3.5 py-2.5">
                            {(
                                [
                                    { value: 'failed', label: 'Com falha' },
                                    { value: 'stuck', label: 'Presas' },
                                    { value: 'all', label: 'Todas' },
                                ] as const
                            ).map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() =>
                                        visitSiblingStatus(tab.value)
                                    }
                                    className={cn(
                                        'rounded-md px-2.5 py-1 text-[11.5px] font-semibold',
                                        siblingStatus === tab.value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {tab.label}
                                </button>
                            ))}
                            <span className="flex-1" />
                            <span className="text-muted-foreground text-[11px]">
                                {siblingExecutions.total}
                            </span>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {siblingExecutions.data.length === 0 ? (
                                <p className="text-muted-foreground p-4 text-xs">
                                    Nenhuma Execução corresponde a este
                                    filtro.
                                </p>
                            ) : (
                                siblingExecutions.data.map((sibling) => {
                                    const isActive =
                                        sibling.id === execution.id;

                                    return (
                                        <Link
                                            key={sibling.id}
                                            href={sibling.traceUrl}
                                            className={cn(
                                                'border-border/70 flex gap-2.5 border-b px-3.5 py-3',
                                                isActive
                                                    ? 'bg-muted border-l-2 border-l-foreground pl-3'
                                                    : 'hover:bg-muted/40',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'mt-1 size-[7px] shrink-0 rounded-full',
                                                    sibling.status ===
                                                        'failed'
                                                        ? 'bg-destructive'
                                                        : sibling.status ===
                                                            'stuck'
                                                          ? 'bg-signal-attention'
                                                          : sibling.status ===
                                                              'completed'
                                                            ? 'bg-signal-live'
                                                            : 'bg-border',
                                                )}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-baseline gap-1.5">
                                                    <span className="text-[12.5px] font-semibold">
                                                        #{shortId(sibling.id)}
                                                    </span>
                                                    <span className="text-muted-foreground truncate text-[11.5px]">
                                                        {sibling.contact
                                                            ?.name ??
                                                            sibling.contact
                                                                ?.phone ??
                                                            'Contato não identificado'}
                                                    </span>
                                                </span>
                                                <span
                                                    className={cn(
                                                        'mt-0.5 block truncate text-[11.5px] font-medium',
                                                        sibling.status ===
                                                            'failed'
                                                            ? 'text-destructive'
                                                            : sibling.status ===
                                                                'stuck'
                                                              ? 'text-signal-attention'
                                                              : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {sibling.statusLabel}
                                                    {sibling.nodeLabel
                                                        ? `: ${sibling.nodeLabel}`
                                                        : ''}
                                                </span>
                                                <span className="text-muted-foreground mt-0.5 block text-[11px]">
                                                    {formatRelative(
                                                        sibling.createdAt,
                                                    )}{' '}
                                                    · v{sibling.version}
                                                </span>
                                            </span>
                                        </Link>
                                    );
                                })
                            )}
                        </div>
                    </aside>

                    <section className="bg-muted/20 flex min-w-0 flex-1 flex-col">
                        <div className="border-border/70 bg-background border-b px-5 py-4">
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-base font-semibold">
                                    Execução #{shortId(execution.id)}
                                </h1>
                                <ExecutionStatusBadge
                                    status={execution.status}
                                    label={execution.statusLabel}
                                />
                            </div>
                            <div className="text-muted-foreground mt-2.5 flex flex-wrap gap-x-5 gap-y-1 text-[11.5px]">
                                <span className="flex items-center gap-1.5">
                                    <UserRound className="size-3.5" />
                                    {contactLabel(execution.contact)}
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <Radio className="size-3.5" />
                                    {execution.channel?.label ?? 'Sem Canal'}
                                </span>
                                <span>v{execution.version}</span>
                                <span className="flex items-center gap-1.5">
                                    <Clock3 className="size-3.5" />
                                    {formatDuration(execution.durationMs)}
                                </span>
                                {execution.aiCostBrl !== undefined && (
                                    <span className="flex items-center gap-1.5">
                                        <Sparkles className="size-3.5" />
                                        {formatCurrency(execution.aiCostBrl)}
                                    </span>
                                )}
                            </div>
                            <p
                                className={cn(
                                    'mt-2.5 text-[12.5px] font-medium',
                                    failed && 'text-destructive',
                                )}
                            >
                                {execution.finalReason === null
                                    ? activeReason(execution.status)
                                    : formatReason(
                                          execution.finalReason,
                                          execution.status,
                                      )}
                            </p>
                        </div>

                        <div className="min-h-0 flex-1 overflow-y-auto p-4">
                            {steps.length === 0 ? (
                                <div className="border-border/70 text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                    A Execução terminou antes de alcançar um
                                    Nó.
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    {steps.map((step) => (
                                        <TraceStepRow
                                            key={step.id}
                                            step={step}
                                        />
                                    ))}
                                </div>
                            )}

                            {unreachedNodes.length > 0 && (
                                <div className="mt-4 grid gap-1.5">
                                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.1em] uppercase">
                                        Não alcançados
                                    </p>
                                    {unreachedNodes.map((node) => (
                                        <div
                                            key={node.nodeId}
                                            className="border-border/70 flex items-center gap-2.5 rounded-lg border border-dashed px-3.5 py-2.5 opacity-60"
                                        >
                                            <span className="bg-border size-2 shrink-0 rounded-full" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-[12.5px] font-medium">
                                                    {node.nodeName}
                                                </span>
                                                <span className="text-muted-foreground block text-[11px]">
                                                    {node.nodeTypeLabel} ·{' '}
                                                    <code className="font-mono">
                                                        {node.nodeId}
                                                    </code>
                                                </span>
                                            </span>
                                            <Badge variant="outline">
                                                Não alcançado
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function TraceStepRow({ step }: { step: TraceStep }) {
    const failed = step.result === 'failed';
    const waiting = step.result === 'waiting' || step.result === 'running';
    const dotColor = failed
        ? 'bg-destructive'
        : waiting
          ? 'bg-signal-attention'
          : 'bg-signal-live';

    return (
        <article
            className={cn(
                'overflow-hidden rounded-[9px] border bg-background',
                step.highlighted && failed
                    ? 'border-destructive border-[1.5px]'
                    : 'border-border/70',
            )}
        >
            <div className="flex items-center gap-2.5 px-3.5 py-2.5">
                <span
                    className={cn('size-2 shrink-0 rounded-full', dotColor)}
                />
                <span className="w-[190px] shrink-0 truncate text-[12.5px] font-semibold">
                    {step.nodeName}
                </span>
                <span
                    className={cn(
                        'min-w-0 flex-1 truncate text-[11.5px]',
                        failed
                            ? 'text-destructive font-medium'
                            : 'text-muted-foreground',
                    )}
                >
                    {failed && step.error !== null
                        ? step.error
                        : step.followedEdge !== null
                          ? `seguiu ${step.followedEdge.label ?? step.followedEdge.id}`
                          : step.resultLabel}
                </span>
                <span className="text-muted-foreground shrink-0 text-[11px]">
                    {formatDuration(step.durationMs)}
                    {step.retryCount ? ` · ${step.retryCount} tentativas` : ''}
                </span>
            </div>

            {step.aiInputTokens !== undefined &&
                step.aiInputTokens !== null && (
                    <div className="flex items-center gap-2 px-3.5 pb-2.5 pl-[46px]">
                        <span className="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-[11px] font-semibold">
                            {step.aiInputTokens + (step.aiOutputTokens ?? 0)}{' '}
                            tokens · {formatCurrency(step.aiCostBrl ?? 0)}
                        </span>
                    </div>
                )}

            {((step.httpRequest !== null && step.httpRequest !== undefined) ||
                (step.aiPrompt !== null && step.aiPrompt !== undefined)) && (
                <details className="border-border/60 border-t">
                    <summary className="hover:bg-muted/45 flex min-h-9 cursor-pointer list-none items-center justify-between gap-3 px-3.5 py-2 text-[11.5px] font-medium">
                        <span>
                            {step.httpRequest !== null &&
                            step.httpRequest !== undefined
                                ? `Payload HTTP${step.httpStatus ? ` · HTTP ${step.httpStatus}` : ''}`
                                : 'Prompt e resposta da IA'}
                        </span>
                        <span className="text-muted-foreground">
                            Somente Owner e Admin
                        </span>
                    </summary>
                    {step.httpRequest !== null &&
                    step.httpRequest !== undefined ? (
                        <div className="grid gap-2.5 border-t p-3.5 lg:grid-cols-2">
                            <TracePayload
                                label="Enviado"
                                value={step.httpRequest}
                                tone={failed ? 'neutral' : 'neutral'}
                            />
                            <TracePayload
                                label="Recebido"
                                value={step.httpResponse}
                                tone={failed ? 'negative' : 'neutral'}
                            />
                        </div>
                    ) : (
                        <div className="grid gap-2.5 border-t p-3.5">
                            <TextPayload label="Prompt" value={step.aiPrompt} />
                            <TextPayload
                                label="Resposta"
                                value={step.aiResponse}
                            />
                            {step.aiToolCalls &&
                                step.aiToolCalls.length > 0 && (
                                    <TracePayload
                                        label="Ferramentas chamadas"
                                        value={step.aiToolCalls}
                                        tone="neutral"
                                    />
                                )}
                        </div>
                    )}
                </details>
            )}

            {Object.keys(step.variablesSnapshot).length > 0 && (
                <details className="border-border/60 border-t">
                    <summary className="hover:bg-muted/45 flex min-h-9 cursor-pointer list-none items-center justify-between gap-3 px-3.5 py-2 text-[11.5px] font-medium">
                        <span>Variáveis após este passo</span>
                        <span className="text-muted-foreground tabular-nums">
                            {Object.keys(step.variablesSnapshot).length}{' '}
                            {Object.keys(step.variablesSnapshot).length === 1
                                ? 'valor'
                                : 'valores'}
                        </span>
                    </summary>
                    <pre className="bg-muted/45 max-h-72 overflow-auto border-t p-3.5 font-mono text-[11px] leading-5">
                        {JSON.stringify(step.variablesSnapshot, null, 2)}
                    </pre>
                </details>
            )}
        </article>
    );
}

function TracePayload({
    label,
    value,
    tone,
}: {
    label: string;
    value: unknown;
    tone: 'neutral' | 'negative';
}) {
    return (
        <div className="min-w-0 overflow-hidden rounded-md border">
            <p className="bg-muted/50 text-muted-foreground border-b px-2.5 py-1.5 text-[11px] font-medium">
                {label}
            </p>
            <pre
                className={cn(
                    'max-h-72 overflow-auto p-2.5 font-mono text-[11px] leading-5',
                    tone === 'negative' ? 'bg-destructive/8' : 'bg-muted/20',
                )}
            >
                {JSON.stringify(value, null, 2)}
            </pre>
        </div>
    );
}

function TextPayload({
    label,
    value,
}: {
    label: string;
    value?: string | null;
}) {
    return (
        <div className="overflow-hidden rounded-md border">
            <p className="bg-muted/50 text-muted-foreground border-b px-2.5 py-1.5 text-[11px] font-medium">
                {label}
            </p>
            <pre className="bg-muted/20 max-h-72 overflow-auto p-2.5 font-mono text-[11px] leading-5 whitespace-pre-wrap">
                {value ?? 'Sem conteúdo.'}
            </pre>
        </div>
    );
}

function ExecutionStatusBadge({
    status,
    label,
}: {
    status: ExecutionStatus;
    label: string;
}) {
    const className =
        status === 'failed' || status === 'abandoned' || status === 'stuck'
            ? 'border-destructive/30 bg-destructive/10 text-destructive'
            : status === 'completed'
              ? 'border-signal-live/35 bg-signal-live/12 text-foreground'
              : 'border-signal-attention/40 bg-signal-attention/12 text-foreground';

    return (
        <Badge variant="outline" className={className}>
            {label}
        </Badge>
    );
}

function shortId(id: string): string {
    return id.replace(/-/g, '').slice(-6).toUpperCase();
}

function contactLabel(contact: Props['execution']['contact']): string {
    if (contact === null) {
        return 'Contato não identificado';
    }

    return contact.name ?? contact.phone;
}

function activeReason(status: ExecutionStatus): string {
    const labels: Partial<Record<ExecutionStatus, string>> = {
        running: 'Percorrendo o Fluxo',
        waiting: 'Aguardando o próximo passo',
        paused: 'Execução pausada',
        stuck: 'Execução sem atividade recente',
        abandoned: 'O Contato não respondeu a tempo',
    };

    return labels[status] ?? 'Sem motivo registrado';
}

function formatReason(reason: string, status: ExecutionStatus): string {
    const labels: Record<string, string> = {
        completed: 'Chegou ao fim do Fluxo',
        contact_timeout: 'O Contato não respondeu a tempo',
        execution_ttl_exceeded: 'A Execução excedeu o tempo máximo',
        node_not_found: 'O Nó atual não existe nesta Versão',
        step_limit_exceeded: 'A Execução atingiu o limite de passos',
        concurrent_execution_limit:
            'A Empresa atingiu o limite de Execuções simultâneas',
        replaced_by_new_trigger: 'Substituída por um novo Gatilho',
    };

    return (
        labels[reason] ??
        (reason.includes('_')
            ? reason.replaceAll('_', ' ')
            : reason || activeReason(status))
    );
}

function formatDuration(durationMs: number): string {
    if (durationMs < 1000) {
        return `${durationMs} ms`;
    }

    if (durationMs < 60_000) {
        return `${new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: durationMs < 10_000 ? 1 : 0,
            maximumFractionDigits: 1,
        }).format(durationMs / 1000)} s`;
    }

    const minutes = Math.floor(durationMs / 60_000);
    const seconds = Math.floor((durationMs % 60_000) / 1000);

    return `${minutes} min ${seconds.toString().padStart(2, '0')} s`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function formatRelative(value: string | null): string {
    if (value === null) return '—';
    const diffSeconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 1000),
    );

    if (diffSeconds < 60) return 'agora';
    if (diffSeconds < 3600) return `há ${Math.floor(diffSeconds / 60)} min`;
    if (diffSeconds < 86_400) return `há ${Math.floor(diffSeconds / 3600)} h`;

    return `há ${Math.floor(diffSeconds / 86_400)} d`;
}
