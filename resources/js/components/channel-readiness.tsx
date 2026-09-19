import { AlertTriangle, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type ChannelStatusValue =
    | 'not_configured'
    | 'connected'
    | 'error'
    | null;

export type ChannelReadiness = {
    status: ChannelStatusValue;
    statusLabel: string | null;
    lastError: string | null;
    credentialsSavedAt: string | null;
    webhookVerifiedAt: string | null;
    lastTestedAt: string | null;
    agent: { id: number; name: string } | null;
};

type MilestoneState = 'done' | 'failed' | 'current' | 'pending';

export type ChannelMilestone = {
    key: 'credentials' | 'webhook' | 'test' | 'agent';
    name: string;
    state: MilestoneState;
    /** Right-aligned moment or value, already formatted for reading. */
    detail: string | null;
    /** Shown only while this milestone is the one waiting on the customer. */
    hint: string | null;
    /** True when the customer completes this step inside Meta's own panel. */
    external: boolean;
};

export type ChannelSignal = {
    tone: 'live' | 'attention' | 'down' | 'idle';
    label: string;
};

export type ChannelNextStep = {
    label: string;
    /** False for a channel that is already fully live, where the action is not urgent. */
    primary: boolean;
};

/**
 * Format a channel moment as the short reading used across the readiness rail.
 */
export function formatChannelMoment(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    const moment = new Date(value);
    const time = new Intl.DateTimeFormat('pt-BR', {
        timeStyle: 'short',
    }).format(moment);

    const isToday = new Date().toDateString() === moment.toDateString();

    if (isToday) {
        return `hoje · ${time}`;
    }

    const day = new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    }).format(moment);

    return `${day} · ${time}`;
}

/**
 * Derive the four milestones that make a WhatsApp channel able to answer.
 *
 * Both the channel catalog and the WhatsApp screen read from this single
 * derivation, so the two surfaces can never disagree about where the customer is.
 */
export function channelMilestones(
    readiness: ChannelReadiness,
): ChannelMilestone[] {
    const testFailed = readiness.status === 'error';

    const milestones: Array<
        Omit<ChannelMilestone, 'state'> & { done: boolean }
    > = [
        {
            key: 'credentials',
            name: 'Credenciais da Meta',
            done: readiness.credentialsSavedAt !== null,
            detail: formatChannelMoment(readiness.credentialsSavedAt),
            hint: 'WABA ID, Phone Number ID, token permanente e App Secret.',
            external: false,
        },
        {
            key: 'webhook',
            name: 'Webhook verificado',
            done: readiness.webhookVerifiedAt !== null,
            detail: formatChannelMoment(readiness.webhookVerifiedAt),
            hint: 'Cole a URL e o verify token no painel do aplicativo Meta. A verificação chega sozinha.',
            external: true,
        },
        {
            key: 'test',
            name: 'Conexão testada',
            done: readiness.lastTestedAt !== null && !testFailed,
            detail: formatChannelMoment(readiness.lastTestedAt),
            hint: 'O teste busca o número, o nome verificado e a qualidade na Meta.',
            external: false,
        },
        {
            key: 'agent',
            name: 'Agente vinculado',
            done: readiness.agent !== null,
            detail: readiness.agent?.name ?? null,
            hint: 'O número recebe mensagens, mas nenhum Agente responde por ele.',
            external: false,
        },
    ];

    let currentTaken = false;

    return milestones.map((milestone): ChannelMilestone => {
        if (milestone.key === 'test' && testFailed) {
            currentTaken = true;

            return {
                ...milestone,
                state: 'failed',
                hint: readiness.lastError,
            };
        }

        if (milestone.done) {
            return { ...milestone, state: 'done', hint: null };
        }

        if (!currentTaken) {
            currentTaken = true;

            return { ...milestone, state: 'current' };
        }

        return { ...milestone, state: 'pending', hint: null, detail: null };
    });
}

/**
 * Reduce a channel to the one state the customer needs to read at a glance.
 */
export function channelSignal(readiness: ChannelReadiness): ChannelSignal {
    if (readiness.status === 'error') {
        return { tone: 'down', label: readiness.statusLabel ?? 'Erro' };
    }

    if (readiness.status === 'connected') {
        return readiness.agent === null
            ? { tone: 'attention', label: 'Sem Agente' }
            : { tone: 'live', label: readiness.statusLabel ?? 'Conectado' };
    }

    return { tone: 'idle', label: readiness.statusLabel ?? 'Não conectado' };
}

/**
 * Name the next action in terms of what actually happens, never "Configurar".
 */
export function channelNextStep(
    milestones: ChannelMilestone[],
): ChannelNextStep {
    const waiting = milestones.find(
        (milestone) =>
            milestone.state === 'current' || milestone.state === 'failed',
    );

    if (waiting === undefined) {
        return { label: 'Configurar', primary: false };
    }

    if (waiting.state === 'failed') {
        return { label: 'Corrigir conexão', primary: true };
    }

    const labels: Record<ChannelMilestone['key'], string> = {
        credentials: 'Conectar WhatsApp',
        webhook: 'Ver URL e token',
        test: 'Testar conexão',
        agent: 'Vincular Agente',
    };

    return { label: labels[waiting.key], primary: true };
}

const signalDotTone: Record<ChannelSignal['tone'], string> = {
    live: 'bg-signal-live ring-signal-live/20',
    attention: 'bg-signal-attention ring-signal-attention/20',
    down: 'bg-destructive ring-destructive/20',
    idle: 'bg-muted-foreground/40 ring-transparent',
};

const signalTextTone: Record<ChannelSignal['tone'], string> = {
    live: 'text-foreground font-medium',
    attention: 'text-foreground font-medium',
    down: 'text-destructive font-medium',
    idle: 'text-muted-foreground',
};

/**
 * The channel state as a lamp plus a word — never colour on its own.
 */
export function ChannelSignalLamp({ signal }: { signal: ChannelSignal }) {
    return (
        <span className="flex shrink-0 items-center gap-2 text-sm">
            <span
                className={cn(
                    'size-2 shrink-0 rounded-full ring-3',
                    signalDotTone[signal.tone],
                )}
            />
            <span className={signalTextTone[signal.tone]}>{signal.label}</span>
        </span>
    );
}

function MilestoneDot({ state }: { state: MilestoneState }) {
    if (state === 'done') {
        return (
            <span className="bg-signal-live text-background flex size-4 shrink-0 items-center justify-center rounded-full">
                <Check className="size-2.5" strokeWidth={3} />
            </span>
        );
    }

    if (state === 'failed') {
        return (
            <span className="bg-destructive flex size-4 shrink-0 items-center justify-center rounded-full text-white">
                <AlertTriangle className="size-2.5" strokeWidth={3} />
            </span>
        );
    }

    if (state === 'current') {
        return (
            <span className="ring-signal-attention/25 flex size-4 shrink-0 items-center justify-center rounded-full ring-3">
                <span className="bg-signal-attention size-2 rounded-full" />
            </span>
        );
    }

    return (
        <span className="flex size-4 shrink-0 items-center justify-center">
            <span className="bg-muted-foreground/30 size-2 rounded-full" />
        </span>
    );
}

/**
 * The readiness rail: the four milestones of a channel, read top to bottom.
 */
export function ChannelReadinessRail({
    milestones,
}: {
    milestones: ChannelMilestone[];
}) {
    return (
        <ol className="grid">
            {milestones.map((milestone, index) => {
                const isLast = index === milestones.length - 1;
                const isSettled = milestone.state === 'done';

                return (
                    <li
                        key={milestone.key}
                        className="grid grid-cols-[1rem_1fr] items-start gap-x-3"
                    >
                        <div className="flex h-full flex-col items-center gap-1">
                            <MilestoneDot state={milestone.state} />
                            {!isLast && (
                                <span
                                    className={cn(
                                        'w-0.5 flex-1 rounded-full',
                                        isSettled
                                            ? 'bg-signal-live/40'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </div>

                        <div
                            className={cn('grid gap-0.5', isLast ? '' : 'pb-4')}
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <span
                                    className={cn(
                                        'text-sm',
                                        milestone.state === 'current' ||
                                            milestone.state === 'failed'
                                            ? 'font-semibold'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {milestone.name}
                                </span>
                                <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                    {milestone.detail ??
                                        (milestone.state === 'current'
                                            ? 'pendente'
                                            : null)}
                                </span>
                            </div>

                            {milestone.hint !== null && (
                                <p
                                    className={cn(
                                        'text-xs',
                                        milestone.state === 'failed'
                                            ? 'text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {milestone.hint}
                                </p>
                            )}
                            {milestone.external &&
                                milestone.state === 'current' && (
                                    <p className="text-muted-foreground text-xs font-medium">
                                        Este passo acontece na Meta
                                    </p>
                                )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
