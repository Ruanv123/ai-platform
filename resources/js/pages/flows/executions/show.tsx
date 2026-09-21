import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CircleDashed,
    Clock3,
    GitBranch,
    Hash,
    Radio,
    UserRound,
    X,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showFlow } from '@/routes/flows';

type ExecutionStatus =
    | 'running'
    | 'waiting'
    | 'paused'
    | 'completed'
    | 'failed'
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
};

type UnreachedNode = {
    nodeId: string;
    nodeName: string;
    nodeType: string;
    nodeTypeLabel: string;
    reached: false;
};

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
    };
    steps: TraceStep[];
    unreachedNodes: UnreachedNode[];
};

export default function FlowExecutionShow({
    execution,
    steps,
    unreachedNodes,
}: Props) {
    const failed = execution.status === 'failed';

    return (
        <>
            <Head title={`Execução · ${execution.flowName}`} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-8">
                <header className="grid gap-6">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            asChild
                            aria-label={`Voltar para ${execution.flowName}`}
                        >
                            <Link href={showFlow(execution.flowId)}>
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <p className="text-muted-foreground text-xs font-medium tracking-[0.14em] uppercase">
                                Traço da Execução
                            </p>
                            <h1 className="truncate text-2xl font-semibold tracking-tight text-balance">
                                {execution.flowName}
                            </h1>
                        </div>
                    </div>

                    <section
                        className={`grid overflow-hidden rounded-2xl border md:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.55fr)] ${
                            failed
                                ? 'border-destructive/35 bg-destructive/5'
                                : 'border-border/70 bg-card'
                        }`}
                    >
                        <div className="grid gap-5 p-5 md:p-6">
                            <div className="flex flex-wrap items-center gap-3">
                                <ExecutionStatusBadge
                                    status={execution.status}
                                    label={execution.statusLabel}
                                />
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {formatDateTime(execution.startedAt)}
                                </span>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs font-medium tracking-[0.12em] uppercase">
                                    Estado final e motivo
                                </p>
                                <p
                                    className={`mt-2 text-lg leading-7 font-semibold text-pretty ${
                                        failed ? 'text-destructive' : ''
                                    }`}
                                >
                                    {execution.finalReason === null
                                        ? activeReason(execution.status)
                                        : formatReason(
                                              execution.finalReason,
                                              execution.status,
                                          )}
                                </p>
                            </div>
                        </div>
                        <dl className="border-border/70 bg-background/55 grid grid-cols-2 border-t md:grid-cols-1 md:border-t-0 md:border-l">
                            <HeaderDatum
                                icon={UserRound}
                                label="Contato"
                                value={contactLabel(execution.contact)}
                                detail={
                                    execution.contact?.phone ?? 'Sem telefone'
                                }
                            />
                            <HeaderDatum
                                icon={Radio}
                                label="Canal"
                                value={execution.channel?.label ?? 'Sem Canal'}
                            />
                            <HeaderDatum
                                icon={Hash}
                                label="Versão"
                                value={`v${execution.version}`}
                            />
                            <HeaderDatum
                                icon={Clock3}
                                label="Duração"
                                value={formatDuration(execution.durationMs)}
                            />
                        </dl>
                    </section>
                </header>

                <main className="grid gap-5">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-[0.14em] uppercase">
                                Percurso
                            </p>
                            <h2 className="mt-1 text-lg font-semibold tracking-tight">
                                Passos em ordem
                            </h2>
                        </div>
                        <span className="text-muted-foreground text-xs tabular-nums">
                            {steps.length}{' '}
                            {steps.length === 1
                                ? 'Nó alcançado'
                                : 'Nós alcançados'}
                        </span>
                    </div>

                    <section aria-label="Passos da Execução">
                        {steps.length === 0 ? (
                            <div className="border-border/70 text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                                A Execução terminou antes de alcançar um Nó.
                            </div>
                        ) : (
                            <ol className="grid">
                                {steps.map((step, index) => (
                                    <TraceStepItem
                                        key={step.id}
                                        step={step}
                                        isLast={
                                            index === steps.length - 1 &&
                                            unreachedNodes.length === 0
                                        }
                                    />
                                ))}
                            </ol>
                        )}

                        {unreachedNodes.length > 0 && (
                            <div className="grid">
                                <div className="grid grid-cols-[2rem_minmax(0,1fr)] gap-3">
                                    <div className="flex flex-col items-center">
                                        <span className="bg-border h-5 w-px" />
                                        <span className="border-muted-foreground/50 bg-background flex size-6 items-center justify-center rounded-full border border-dashed">
                                            <CircleDashed className="text-muted-foreground size-3" />
                                        </span>
                                        <span className="bg-border min-h-5 w-px flex-1" />
                                    </div>
                                    <div className="py-3">
                                        <p className="text-muted-foreground text-xs font-medium tracking-[0.12em] uppercase">
                                            Não alcançados
                                        </p>
                                    </div>
                                </div>
                                {unreachedNodes.map((node, index) => (
                                    <UnreachedNodeItem
                                        key={node.nodeId}
                                        node={node}
                                        isLast={
                                            index === unreachedNodes.length - 1
                                        }
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                </main>
            </div>
        </>
    );
}

function TraceStepItem({ step, isLast }: { step: TraceStep; isLast: boolean }) {
    const failed = step.result === 'failed';
    const waiting = step.result === 'waiting' || step.result === 'running';

    return (
        <li className="grid grid-cols-[2rem_minmax(0,1fr)] gap-3">
            <div className="flex flex-col items-center" aria-hidden="true">
                <span
                    className={`h-5 w-px ${step.sequence === 1 ? 'bg-transparent' : 'bg-border'}`}
                />
                <span
                    className={`flex size-6 items-center justify-center rounded-full border ${
                        failed
                            ? 'border-destructive/50 bg-destructive text-white'
                            : waiting
                              ? 'border-signal-attention/60 bg-signal-attention text-foreground'
                              : 'border-signal-live/50 bg-signal-live text-background'
                    }`}
                >
                    {failed ? (
                        <X className="size-3.5" />
                    ) : waiting ? (
                        <AlertTriangle className="size-3.5" />
                    ) : (
                        <Check className="size-3.5" />
                    )}
                </span>
                <span
                    className={`min-h-5 w-px flex-1 ${isLast ? 'bg-transparent' : 'bg-border'}`}
                />
            </div>

            <article
                className={`mb-3 overflow-hidden rounded-xl border ${
                    step.highlighted
                        ? failed
                            ? 'border-destructive/45 bg-destructive/5 shadow-[0_0_0_3px_color-mix(in_oklch,var(--destructive)_10%,transparent)]'
                            : 'border-signal-attention/45 bg-signal-attention/5 shadow-[0_0_0_3px_color-mix(in_oklch,var(--signal-attention)_10%,transparent)]'
                        : 'border-border/70 bg-card'
                }`}
            >
                <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-xs font-medium tabular-nums">
                                {String(step.sequence).padStart(2, '0')}
                            </span>
                            <h3 className="font-semibold tracking-tight">
                                {step.nodeName}
                            </h3>
                            {step.highlighted && (
                                <Badge
                                    variant={failed ? 'destructive' : 'outline'}
                                    className={
                                        failed
                                            ? undefined
                                            : 'border-signal-attention/50 text-foreground'
                                    }
                                >
                                    {failed ? 'Quebrou aqui' : 'Parou aqui'}
                                </Badge>
                            )}
                        </div>
                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                            <span>{step.nodeTypeLabel}</span>
                            <span aria-hidden="true">·</span>
                            <code className="font-mono">{step.nodeId}</code>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-3 text-xs">
                        <ResultLabel
                            result={step.result}
                            label={step.resultLabel}
                        />
                        <span className="text-muted-foreground tabular-nums">
                            {formatDuration(step.durationMs)}
                        </span>
                    </div>
                </div>

                {step.error !== null && (
                    <div className="border-destructive/20 bg-destructive/8 text-destructive border-t px-4 py-3 text-sm leading-6">
                        {step.error}
                    </div>
                )}

                {step.followedEdge !== null && (
                    <div className="border-border/60 text-muted-foreground flex items-center gap-2 border-t px-4 py-3 text-xs">
                        <GitBranch className="size-3.5 shrink-0" />
                        <span>Seguiu</span>
                        <strong className="text-foreground font-medium">
                            {step.followedEdge.label ?? step.followedEdge.id}
                        </strong>
                        {step.followedEdge.target !== null && (
                            <>
                                <span aria-hidden="true">→</span>
                                <code className="truncate font-mono">
                                    {step.followedEdge.target}
                                </code>
                            </>
                        )}
                    </div>
                )}

                <details className="border-border/60 border-t">
                    <summary className="hover:bg-muted/45 focus-visible:ring-ring flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none">
                        <span>Variáveis após este passo</span>
                        <span className="text-muted-foreground tabular-nums">
                            {Object.keys(step.variablesSnapshot).length}{' '}
                            {Object.keys(step.variablesSnapshot).length === 1
                                ? 'valor'
                                : 'valores'}
                        </span>
                    </summary>
                    <pre className="bg-muted/45 max-h-80 overflow-auto border-t p-4 font-mono text-xs leading-5">
                        {JSON.stringify(step.variablesSnapshot, null, 2)}
                    </pre>
                </details>
            </article>
        </li>
    );
}

function UnreachedNodeItem({
    node,
    isLast,
}: {
    node: UnreachedNode;
    isLast: boolean;
}) {
    return (
        <div className="grid grid-cols-[2rem_minmax(0,1fr)] gap-3 opacity-55">
            <div className="flex flex-col items-center" aria-hidden="true">
                <span className="bg-border h-4 w-px" />
                <span className="border-muted-foreground/50 bg-background size-3 rounded-full border border-dashed" />
                <span
                    className={`min-h-4 w-px flex-1 ${isLast ? 'bg-transparent' : 'bg-border'}`}
                />
            </div>
            <div className="border-border/70 mb-2 flex items-center justify-between gap-4 rounded-lg border border-dashed px-4 py-3">
                <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                        {node.nodeName}
                    </p>
                    <p className="text-muted-foreground mt-0.5 truncate text-xs">
                        {node.nodeTypeLabel} ·{' '}
                        <code className="font-mono">{node.nodeId}</code>
                    </p>
                </div>
                <Badge variant="outline">Não alcançado</Badge>
            </div>
        </div>
    );
}

function HeaderDatum({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof UserRound;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="border-border/60 flex min-w-0 items-start gap-3 border-b p-4 last:border-b-0 odd:border-r md:odd:border-r-0">
            <Icon className="text-muted-foreground mt-0.5 size-4 shrink-0" />
            <div className="min-w-0">
                <dt className="text-muted-foreground text-[11px] font-medium tracking-[0.1em] uppercase">
                    {label}
                </dt>
                <dd className="mt-0.5 truncate text-sm font-medium tabular-nums">
                    {value}
                </dd>
                {detail !== undefined && (
                    <dd className="text-muted-foreground mt-0.5 truncate text-xs tabular-nums">
                        {detail}
                    </dd>
                )}
            </div>
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
        status === 'failed' || status === 'stuck'
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

function ResultLabel({
    result,
    label,
}: {
    result: TraceStep['result'];
    label: string;
}) {
    return (
        <span
            className={
                result === 'failed'
                    ? 'text-destructive font-medium'
                    : result === 'completed'
                      ? 'text-foreground font-medium'
                      : 'text-signal-attention font-medium'
            }
        >
            {label}
        </span>
    );
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

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}
