import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Bot,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Inbox,
    PanelRight,
    PanelRightClose,
    Search,
    Send,
    UserRound,
    Users,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInboxRealtime } from '@/hooks/use-inbox-realtime';
import {
    index as inboxIndex,
    returnMethod,
    show as inboxShow,
    take,
} from '@/routes/inbox';
import { store as messagesStore } from '@/routes/inbox/messages';

type Conversation = {
    id: number;
    name: string | null;
    phone: string;
    excerpt: string;
    lastMessageAt: string | null;
    waitingSince: string | null;
    waitingSeconds: number;
    reason: { code: string; label: string };
    channel: { id: string; label: string } | null;
    assignee: { id: number; name: string } | null;
};

type Message = {
    id: number;
    direction: 'incoming' | 'outgoing';
    sender: string;
    content: string | null;
    payloadType: string;
    channel: string | null;
    createdAt: string | null;
    deliveryStatus: string | null;
};

type MessagePage = {
    data: Message[];
    currentPage: number;
    lastPage: number;
    nextUrl: string | null;
    previousUrl: string | null;
};

type SelectedConversation = {
    contact: {
        id: number;
        name: string | null;
        phone: string;
        fields: { name: string; value: string | null; sensitive: boolean }[];
        tags: { id: string; name: string }[];
    };
    channel: { id: string; label: string };
    assignee: { id: number; name: string } | null;
    reason: { code: string; label: string };
    execution: {
        id: string;
        flowId: string;
        flowName: string;
        version: number;
        nodeId: string | null;
        nodeName: string;
        traceUrl: string;
        status: string;
    };
    messages: MessagePage;
    history: MessagePage;
    window: {
        open: boolean;
        expiresAt: string | null;
        remainingSeconds: number | null;
    };
    canWrite: boolean;
};

type Props = {
    conversations: {
        data: Conversation[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    counts: { awaiting: number; mine: number; all: number };
    filters: {
        queue: string;
        assignedTo: string | number | null;
        channel: string | null;
        search: string;
    };
    members: { id: number; name: string }[];
    channels: { id: string; label: string }[];
    selected: SelectedConversation | null;
    currentUserId: number;
};

const realtimeProps = ['conversations', 'counts', 'selected'];

export default function InboxPage({
    conversations,
    counts,
    filters,
    members,
    channels,
    selected,
    currentUserId,
}: Props) {
    const { props } = usePage<{ currentTeam?: { id: number } | null }>();
    const [search, setSearch] = useState(filters.search);
    const [showDetails, setShowDetails] = useState(true);
    const [messageType, setMessageType] = useState<'text' | 'template'>('text');
    const teamId = props.currentTeam?.id ?? null;
    useInboxRealtime(teamId, realtimeProps);

    const updateFilters = (next: Record<string, string | undefined>) => {
        const query = {
            queue: filters.queue,
            assigned_to: filters.assignedTo?.toString() || undefined,
            channel: filters.channel || undefined,
            search: filters.search || undefined,
            ...next,
        };

        router.get(inboxIndex.url({ query }), undefined, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        updateFilters({ search: search || undefined });
    };

    return (
        <>
            <Head title="Atendimento" />
            <div className="flex min-h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Atendimento"
                        description="Conversas que um Fluxo transferiu para a Equipe."
                    />
                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Inbox className="size-4" />
                        <span>
                            {counts.awaiting} aguardando sem responsável
                        </span>
                    </div>
                </header>

                <section className="border-border/70 bg-card grid gap-3 rounded-xl border p-3 lg:grid-cols-[auto_1fr_auto_auto] lg:items-center">
                    <div className="flex flex-wrap gap-1">
                        <QueueButton
                            active={filters.queue === 'awaiting'}
                            count={counts.awaiting}
                            onClick={() => updateFilters({ queue: 'awaiting' })}
                        >
                            Aguardando
                        </QueueButton>
                        <QueueButton
                            active={filters.queue === 'mine'}
                            count={counts.mine}
                            onClick={() => updateFilters({ queue: 'mine' })}
                        >
                            Minhas
                        </QueueButton>
                        <QueueButton
                            active={filters.queue === 'all'}
                            count={counts.all}
                            onClick={() => updateFilters({ queue: 'all' })}
                        >
                            Todas
                        </QueueButton>
                    </div>
                    <form onSubmit={submitSearch} className="relative min-w-0">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por nome ou telefone"
                            className="pl-9"
                            aria-label="Buscar por nome ou telefone"
                        />
                    </form>
                    <select
                        value={filters.assignedTo?.toString() ?? ''}
                        onChange={(event) =>
                            updateFilters({
                                assigned_to: event.target.value || undefined,
                            })
                        }
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        aria-label="Atribuída a"
                    >
                        <option value="">Atribuída a</option>
                        <option value="unassigned">Não atribuídas</option>
                        {members.map((member) => (
                            <option key={member.id} value={member.id}>
                                {member.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.channel ?? ''}
                        onChange={(event) =>
                            updateFilters({
                                channel: event.target.value || undefined,
                            })
                        }
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        aria-label="Canal"
                    >
                        <option value="">Todos os Canais</option>
                        {channels.map((channel) => (
                            <option key={channel.id} value={channel.id}>
                                {channel.label}
                            </option>
                        ))}
                    </select>
                </section>

                <main className="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(19rem,25rem)_minmax(0,1fr)]">
                    <ConversationList conversations={conversations.data} />
                    {selected === null ? (
                        <EmptySelection />
                    ) : (
                        <ConversationThread
                            key={selected.contact.id}
                            selected={selected}
                            currentUserId={currentUserId}
                            showDetails={showDetails}
                            onToggleDetails={() =>
                                setShowDetails((value) => !value)
                            }
                            messageType={messageType}
                            onMessageTypeChange={setMessageType}
                        />
                    )}
                </main>
            </div>
        </>
    );
}

function QueueButton({
    active,
    count,
    children,
    onClick,
}: {
    active: boolean;
    count: number;
    children: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            }`}
        >
            {children} <span className="tabular-nums">{count}</span>
        </button>
    );
}

function ConversationList({
    conversations,
}: {
    conversations: Conversation[];
}) {
    if (conversations.length === 0) {
        return (
            <section className="border-border/70 bg-card flex min-h-80 flex-col items-center justify-center gap-3 rounded-xl border p-8 text-center">
                <span className="bg-muted flex size-11 items-center justify-center rounded-full">
                    <Check className="text-muted-foreground size-5" />
                </span>
                <div>
                    <h2 className="font-semibold">Tudo em dia</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Nenhuma conversa corresponde a esta fila.
                    </p>
                </div>
            </section>
        );
    }

    return (
        <section className="border-border/70 bg-card min-h-0 overflow-hidden rounded-xl border">
            <div className="border-border/70 text-muted-foreground border-b px-4 py-3 text-xs font-medium tracking-[0.12em] uppercase">
                Conversas transferidas
            </div>
            <div className="divide-border/70 divide-y">
                {conversations.map((conversation) => (
                    <Link
                        key={conversation.id}
                        href={inboxShow(conversation.id)}
                        className="hover:bg-muted/40 focus-visible:bg-muted/50 block px-4 py-4 transition-colors outline-none"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {conversation.name ?? conversation.phone}
                                </p>
                                {conversation.name !== null && (
                                    <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                        {conversation.phone}
                                    </p>
                                )}
                            </div>
                            <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                {formatWaiting(conversation.waitingSeconds)}
                            </span>
                        </div>
                        <p className="text-muted-foreground mt-2 truncate text-sm">
                            {conversation.excerpt}
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="text-[11px]">
                                {conversation.reason.label}
                            </Badge>
                            {conversation.assignee !== null && (
                                <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                    <UserRound className="size-3" />
                                    {conversation.assignee.name}
                                </span>
                            )}
                            {conversation.channel !== null && (
                                <span className="text-muted-foreground ml-auto text-xs">
                                    {conversation.channel.label}
                                </span>
                            )}
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function EmptySelection() {
    return (
        <section className="border-border/70 bg-card flex min-h-80 flex-col items-center justify-center gap-3 rounded-xl border p-8 text-center">
            <span className="bg-muted flex size-12 items-center justify-center rounded-full">
                <Users className="text-muted-foreground size-5" />
            </span>
            <div>
                <h2 className="font-semibold">Escolha uma conversa</h2>
                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                    Leia o motivo da transferência e o histórico antes de entrar
                    no atendimento.
                </p>
            </div>
        </section>
    );
}

function ConversationThread({
    selected,
    currentUserId,
    showDetails,
    onToggleDetails,
    messageType,
    onMessageTypeChange,
}: {
    selected: SelectedConversation;
    currentUserId: number;
    showDetails: boolean;
    onToggleDetails: () => void;
    messageType: 'text' | 'template';
    onMessageTypeChange: (type: 'text' | 'template') => void;
}) {
    const form = useForm({
        type: messageType,
        content: '',
        template: '',
        parameters: '',
    });
    const assignedToMe = selected.assignee?.id === currentUserId;

    const sendMessage = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            type: messageType,
            parameters: data.parameters
                .split('\n')
                .map((parameter) => parameter.trim())
                .filter(Boolean),
        }));
        form.post(messagesStore(selected.contact.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset('content', 'template', 'parameters'),
        });
    };

    const takeConversation = () => {
        router.post(take(selected.contact.id).url, undefined, {
            preserveScroll: true,
        });
    };

    const returnConversation = () => {
        router.post(returnMethod(selected.contact.id).url);
    };

    return (
        <section className="border-border/70 bg-card grid min-h-0 min-w-0 overflow-hidden rounded-xl border xl:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)]">
            <div className="flex min-h-0 min-w-0 flex-col">
                <header className="border-border/70 flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            asChild
                            className="xl:hidden"
                        >
                            <Link
                                href={inboxIndex()}
                                aria-label="Voltar para a lista"
                            >
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <div className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-full">
                            <UserRound className="text-muted-foreground size-4" />
                        </div>
                        <div className="min-w-0">
                            <h2 className="truncate font-semibold">
                                {selected.contact.name ??
                                    selected.contact.phone}
                            </h2>
                            <p className="text-muted-foreground truncate text-xs">
                                {selected.contact.name !== null
                                    ? `${selected.contact.phone} · ${selected.channel.label}`
                                    : selected.channel.label}
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onToggleDetails}
                        aria-label={
                            showDetails ? 'Fechar ficha' : 'Abrir ficha'
                        }
                    >
                        {showDetails ? <PanelRightClose /> : <PanelRight />}
                    </Button>
                </header>

                <div className="border-border/70 bg-muted/20 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 text-sm">
                    <div className="min-w-0">
                        <p className="text-muted-foreground text-xs font-medium tracking-[0.1em] uppercase">
                            Motivo da transferência
                        </p>
                        <p className="mt-1 font-medium">
                            {selected.reason.label}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {selected.assignee === null ? (
                            <Button size="sm" onClick={takeConversation}>
                                <UserRound />
                                Assumir
                            </Button>
                        ) : (
                            <span className="text-muted-foreground flex items-center gap-1 text-xs">
                                <UserRound className="size-3.5" />
                                {assignedToMe
                                    ? 'Você assumiu esta conversa'
                                    : `${selected.assignee.name} assumiu esta conversa`}
                            </span>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={returnConversation}
                        >
                            <Bot />
                            Devolver ao bot
                        </Button>
                    </div>
                </div>

                <div className="bg-muted/10 min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    {selected.messages.data.map((message) => (
                        <MessageBubble key={message.id} message={message} />
                    ))}
                    <HistoryPanel history={selected.history} />
                </div>

                <div className="border-border/70 border-t p-4">
                    {!selected.canWrite && (
                        <div className="border-signal-attention/40 bg-signal-attention/10 text-foreground mb-3 rounded-lg border px-3 py-2 text-sm">
                            {selected.assignee === null
                                ? 'Assuma a conversa antes de escrever.'
                                : `${selected.assignee.name} está atendendo esta conversa. Você precisa assumir antes de escrever.`}
                        </div>
                    )}
                    {selected.window.open ? (
                        <p className="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs">
                            <Clock3 className="size-3.5" />
                            Janela de 24 horas:{' '}
                            {formatRemaining(selected.window.remainingSeconds)}
                        </p>
                    ) : (
                        <div className="border-destructive/35 bg-destructive/8 text-destructive mb-3 rounded-lg border px-3 py-2 text-sm">
                            A janela de 24 horas terminou. Respostas livres
                            estão bloqueadas; envie um Template aprovado.
                        </div>
                    )}
                    <form onSubmit={sendMessage} className="grid gap-2">
                        <div className="flex gap-1">
                            <button
                                type="button"
                                onClick={() => onMessageTypeChange('text')}
                                className={`rounded px-2 py-1 text-xs font-medium ${messageType === 'text' ? 'bg-muted text-foreground' : 'text-muted-foreground'}`}
                            >
                                Resposta livre
                            </button>
                            <button
                                type="button"
                                onClick={() => onMessageTypeChange('template')}
                                className={`rounded px-2 py-1 text-xs font-medium ${messageType === 'template' ? 'bg-muted text-foreground' : 'text-muted-foreground'}`}
                            >
                                Template
                            </button>
                        </div>
                        {messageType === 'template' ? (
                            <>
                                <Input
                                    value={form.data.template}
                                    onChange={(event) =>
                                        form.setData(
                                            'template',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nome do Template aprovado"
                                    disabled={!assignedToMe || form.processing}
                                />
                                <textarea
                                    value={form.data.parameters}
                                    onChange={(event) =>
                                        form.setData(
                                            'parameters',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Um parâmetro por linha (opcional)"
                                    className="border-input placeholder:text-muted-foreground min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2"
                                    disabled={!assignedToMe || form.processing}
                                />
                            </>
                        ) : (
                            <textarea
                                value={form.data.content}
                                onChange={(event) =>
                                    form.setData('content', event.target.value)
                                }
                                placeholder="Escreva uma resposta..."
                                className="border-input placeholder:text-muted-foreground min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                                disabled={
                                    !selected.canWrite ||
                                    !selected.window.open ||
                                    form.processing
                                }
                            />
                        )}
                        {form.errors.content && (
                            <p className="text-destructive text-xs">
                                {form.errors.content}
                            </p>
                        )}
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-muted-foreground text-xs">
                                {selected.window.open
                                    ? 'Enviada pelo mesmo Canal.'
                                    : 'Somente Template.'}
                            </p>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    !assignedToMe ||
                                    form.processing ||
                                    (messageType === 'text' &&
                                        !selected.window.open)
                                }
                            >
                                <Send />
                                Enviar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            {showDetails && <ContactPanel selected={selected} />}
        </section>
    );
}

function MessageBubble({ message }: { message: Message }) {
    const outgoing = message.direction === 'outgoing';

    return (
        <div className={`flex ${outgoing ? 'justify-end' : 'justify-start'}`}>
            <article
                className={`max-w-[85%] rounded-xl border px-3 py-2 text-sm ${outgoing ? 'border-primary/20 bg-primary/10' : 'border-border/70 bg-card'}`}
            >
                <div className="mb-1 flex items-center gap-2 text-[11px]">
                    <span className="font-medium">{message.sender}</span>
                    <span className="text-muted-foreground">
                        {formatDate(message.createdAt)}
                    </span>
                </div>
                <p className="break-words whitespace-pre-wrap">
                    {message.content ?? `[${message.payloadType}]`}
                </p>
            </article>
        </div>
    );
}

function HistoryPanel({ history }: { history: MessagePage }) {
    return (
        <details className="border-border/70 bg-card overflow-hidden rounded-xl border">
            <summary className="hover:bg-muted/40 cursor-pointer px-4 py-3 text-sm font-medium">
                Histórico completo · somente leitura
            </summary>
            <div className="border-border/70 grid gap-2 border-t p-3">
                {history.data.map((message) => (
                    <div
                        key={message.id}
                        className="flex items-center justify-between gap-3 text-xs"
                    >
                        <span className="truncate">
                            <strong>{message.sender}</strong> ·{' '}
                            {message.content ?? `[${message.payloadType}]`}
                        </span>
                        <span className="text-muted-foreground shrink-0">
                            {message.channel}
                        </span>
                    </div>
                ))}
                <Pagination page={history} />
            </div>
        </details>
    );
}

function ContactPanel({ selected }: { selected: SelectedConversation }) {
    return (
        <aside className="border-border/70 bg-muted/10 min-h-0 overflow-y-auto border-t p-4 xl:border-t-0 xl:border-l">
            <div className="grid gap-6">
                <section>
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.12em] uppercase">
                        Ficha do Contato
                    </p>
                    <dl className="mt-3 grid gap-3">
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Nome
                            </dt>
                            <dd className="mt-0.5 text-sm font-medium">
                                {selected.contact.name ?? 'Sem nome'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                Telefone
                            </dt>
                            <dd className="mt-0.5 text-sm font-medium tabular-nums">
                                {selected.contact.phone}
                            </dd>
                        </div>
                        {selected.contact.fields.map((field) => (
                            <div key={field.name}>
                                <dt className="text-muted-foreground text-xs">
                                    {field.name}
                                </dt>
                                <dd className="mt-0.5 text-sm font-medium">
                                    {field.value ?? '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {selected.contact.tags.length > 0 && (
                        <div className="mt-4 flex flex-wrap gap-1.5">
                            {selected.contact.tags.map((tag) => (
                                <Badge key={tag.id} variant="secondary">
                                    {tag.name}
                                </Badge>
                            ))}
                        </div>
                    )}
                </section>
                <section className="border-border/70 border-t pt-4">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.12em] uppercase">
                        Execução pausada
                    </p>
                    <div className="mt-3 grid gap-2 text-sm">
                        <p className="font-medium">
                            {selected.execution.flowName}
                        </p>
                        <p className="text-muted-foreground">
                            Versão {selected.execution.version} · Nó{' '}
                            {selected.execution.nodeName}
                        </p>
                        <Link
                            href={selected.execution.traceUrl}
                            className="text-primary text-xs underline underline-offset-4"
                        >
                            Abrir traço da Execução
                        </Link>
                    </div>
                </section>
            </div>
        </aside>
    );
}

function Pagination({ page }: { page: MessagePage }) {
    if (page.lastPage <= 1) return null;

    return (
        <div className="flex items-center justify-between pt-2">
            {page.previousUrl ? (
                <Link
                    href={page.previousUrl}
                    className="text-primary inline-flex items-center text-xs"
                >
                    <ChevronLeft className="size-3" />
                    Anterior
                </Link>
            ) : (
                <span />
            )}
            <span className="text-muted-foreground text-xs">
                {page.currentPage} / {page.lastPage}
            </span>
            {page.nextUrl ? (
                <Link
                    href={page.nextUrl}
                    className="text-primary inline-flex items-center text-xs"
                >
                    Próxima
                    <ChevronRight className="size-3" />
                </Link>
            ) : (
                <span />
            )}
        </div>
    );
}

function formatWaiting(seconds: number): string {
    if (seconds < 60) return 'agora';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min`;
    return `${Math.floor(seconds / 3600)} h`;
}

function formatRemaining(seconds: number | null): string {
    if (seconds === null) return 'aberta';
    if (seconds <= 0) return 'encerrada';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return hours > 0
        ? `${hours}h ${minutes}min restantes`
        : `${minutes}min restantes`;
}

function formatDate(value: string | null): string {
    if (value === null) return '';
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
