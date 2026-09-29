import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Bot,
    ChevronLeft,
    ChevronRight,
    Clock3,
    FileText,
    ImagePlus,
    Mic,
    PanelRight,
    PanelRightClose,
    Search,
    Send,
    UserRound,
    Users,
    Video,
} from 'lucide-react';
import {
    type ChangeEvent,
    type FormEvent,
    type ReactNode,
    useCallback,
    useRef,
    useState,
} from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInboxRealtime } from '@/hooks/use-inbox-realtime';
import { cn } from '@/lib/utils';
import {
    index as inboxIndex,
    returnMethod,
    show as inboxShow,
    take,
} from '@/routes/inbox';
import {
    pause as pauseAgent,
    takeOver as takeOverAgent,
} from '@/routes/inbox/agent';
import { store as messagesStore } from '@/routes/inbox/messages';
import { VoiceMessageRecorder } from '@/components/inbox-voice-recorder';

type Conversation = {
    contactId: number;
    name: string | null;
    phone: string;
    excerpt: string;
    lastMessageAt: string | null;
    unreadCount: number;
    waitingSince: string | null;
    waitingSeconds: number;
    state: 'agent' | 'flow';
    agentPaused: boolean;
    reason: { code: string; label: string } | null;
    channel: {
        id: string;
        label: string;
        status: 'connected' | 'error' | 'not_configured';
        statusLabel: string;
    };
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
    failureReason: string | null;
    reactionEmoji: string | null;
    reactedAt: string | null;
    mediaAvailable: boolean;
    mediaUrl: string | null;
    mediaFilename: string | null;
    mediaMimeType: string | null;
};

const supportedMessageTypes = new Set([
    'text',
    'template',
    'image',
    'audio',
    'video',
    'document',
]);

function isUnsupportedMessage(message: Message): boolean {
    return !supportedMessageTypes.has(message.payloadType);
}

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
        consents: Record<
            string,
            {
                purpose: string;
                label: string;
                granted: boolean;
                action: string | null;
                source: string | null;
                at: string | null;
            }
        >;
    };
    channel: {
        id: string;
        label: string;
        status: 'connected' | 'error' | 'not_configured';
        statusLabel: string;
        available: boolean;
    };
    state: 'agent' | 'flow';
    agentPaused: boolean;
    agentPauseExpiresAt: string | null;
    assignee: { id: number; name: string } | null;
    reason: { code: string; label: string } | null;
    execution: {
        id: string;
        flowId: string;
        flowName: string;
        version: number;
        nodeId: string | null;
        nodeName: string;
        traceUrl: string;
        status: string;
    } | null;
    messages: MessagePage;
    history: MessagePage;
    window: {
        open: boolean;
        expiresAt: string | null;
        remainingSeconds: number | null;
    };
    canWrite: boolean;
    canSend: boolean;
    canPause: boolean;
    canTakeOver: boolean;
    canReturn: boolean;
    templates: {
        name: string;
        language: string;
        headerType: string | null;
        headerText: string | null;
    }[];
};

function failureReasonLabel(failureReason: string | null): string {
    const translatedReasons: Record<string, string> = {
        customer_care_window_closed:
            'Janela de 24 h encerrada · use um Template',
        agent_response_failed: 'Falha na resposta do Agente · pausa mantida',
        provider_error: 'Falha no envio · tente novamente',
    };

    return (
        translatedReasons[failureReason ?? ''] ??
        failureReason ??
        'Falha no envio · tente novamente'
    );
}

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
        state: string;
        search: string;
    };
    members: { id: number; name: string }[];
    channels: {
        id: string;
        label: string;
        status: 'connected' | 'error' | 'not_configured';
        statusLabel: string;
    }[];
    selected: SelectedConversation | null;
    currentUserId: number;
};

type MessageType =
    | 'text'
    | 'template'
    | 'image'
    | 'document'
    | 'video'
    | 'audio';

type OutgoingMediaOption = {
    label: string;
    accept: string;
    extensions: string[];
    mimeTypes: string[];
    maxBytes: number;
    maxLabel: string;
    invalidTypeMessage: string;
    invalidSizeMessage: string;
};

const outgoingMediaOptions: Record<
    'image' | 'document' | 'video' | 'audio',
    OutgoingMediaOption
> = {
    image: {
        label: 'Imagem',
        accept: '.jpg,.jpeg,.png,image/jpeg,image/png',
        extensions: ['jpg', 'jpeg', 'png'],
        mimeTypes: ['image/jpeg', 'image/png'],
        maxBytes: 5 * 1024 * 1024,
        maxLabel: '5 MB',
        invalidTypeMessage: 'Escolha uma imagem JPEG ou PNG.',
        invalidSizeMessage: 'A imagem deve ter no máximo 5 MB.',
    },
    document: {
        label: 'Documento',
        accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
        extensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
        mimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ],
        maxBytes: 100 * 1024 * 1024,
        maxLabel: '100 MB',
        invalidTypeMessage:
            'Escolha um documento PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
        invalidSizeMessage: 'O documento deve ter no máximo 100 MB.',
    },
    video: {
        label: 'Vídeo',
        accept: '.mp4,.3gp,video/mp4,video/3gpp',
        extensions: ['mp4', '3gp'],
        mimeTypes: ['video/mp4', 'video/3gpp'],
        maxBytes: 16 * 1024 * 1024,
        maxLabel: '16 MB',
        invalidTypeMessage: 'Escolha um vídeo MP4 ou 3GP.',
        invalidSizeMessage: 'O vídeo deve ter no máximo 16 MB.',
    },
    audio: {
        label: 'Áudio',
        accept: '.ogg,.opus,.mp3,.aac,.mp4,.m4a,audio/ogg,audio/mpeg,audio/aac,audio/mp4,video/mp4',
        extensions: ['ogg', 'opus', 'mp3', 'aac', 'mp4', 'm4a'],
        mimeTypes: [
            'audio/ogg',
            'application/ogg',
            'audio/opus',
            'audio/mpeg',
            'audio/mp3',
            'audio/aac',
            'audio/x-aac',
            'audio/mp4',
            'video/mp4',
        ],
        maxBytes: 16 * 1024 * 1024,
        maxLabel: '16 MB',
        invalidTypeMessage: 'Escolha um áudio OGG/Opus, MP3, AAC ou MP4.',
        invalidSizeMessage: 'O áudio deve ter no máximo 16 MB.',
    },
};

const deliveryStatusLabels: Record<string, string> = {
    pending: 'Aguardando envio',
    sent: 'Enviada',
    delivered: 'Entregue',
    read: 'Lida',
    failed: 'Falhou',
};

function outgoingMediaValidationMessage(
    file: File,
    type: 'image' | 'document' | 'video' | 'audio',
): string | null {
    const option = outgoingMediaOptions[type];
    const extension = file.name.split('.').pop()?.toLowerCase();
    const mimeType = file.type.split(';')[0]?.trim().toLowerCase() ?? '';
    if (
        !option.extensions.includes(extension ?? '') ||
        (mimeType !== '' && !option.mimeTypes.includes(mimeType))
    ) {
        return option.invalidTypeMessage;
    }

    if (file.size > option.maxBytes) {
        return option.invalidSizeMessage;
    }

    return null;
}

function isOutgoingMediaType(
    type: MessageType,
): type is keyof typeof outgoingMediaOptions {
    return (
        type === 'image' ||
        type === 'document' ||
        type === 'video' ||
        type === 'audio'
    );
}

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
    const teamId = props.currentTeam?.id ?? null;
    useInboxRealtime(teamId, realtimeProps);

    const updateFilters = (next: Record<string, string | undefined>) => {
        const query = {
            queue: filters.queue,
            assigned_to: filters.assignedTo?.toString() || undefined,
            channel: filters.channel || undefined,
            state: filters.state || undefined,
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
            <div className="flex h-full min-h-0 flex-1 flex-col overflow-hidden xl:flex-row">
                <ConversationsPanel
                    conversations={conversations.data}
                    counts={counts}
                    filters={filters}
                    members={members}
                    channels={channels}
                    search={search}
                    onSearchChange={setSearch}
                    onSubmitSearch={submitSearch}
                    onUpdateFilters={updateFilters}
                    selectedContactId={selected?.contact.id ?? null}
                    selectedChannelId={selected?.channel.id ?? null}
                    currentUserId={currentUserId}
                    className={selected !== null ? 'hidden xl:flex' : undefined}
                />

                {selected === null ? (
                    <EmptySelection />
                ) : (
                    <SelectedConversationView
                        key={`${selected.contact.id}:${selected.channel.id}`}
                        selected={selected}
                        currentUserId={currentUserId}
                    />
                )}
            </div>
        </>
    );
}

function ConversationsPanel({
    conversations,
    counts,
    filters,
    members,
    channels,
    search,
    onSearchChange,
    onSubmitSearch,
    onUpdateFilters,
    selectedContactId,
    selectedChannelId,
    currentUserId,
    className,
}: {
    conversations: Conversation[];
    counts: { awaiting: number; mine: number; all: number };
    filters: Props['filters'];
    members: Props['members'];
    channels: Props['channels'];
    search: string;
    onSearchChange: (value: string) => void;
    onSubmitSearch: (event: FormEvent<HTMLFormElement>) => void;
    onUpdateFilters: (next: Record<string, string | undefined>) => void;
    selectedContactId: number | null;
    selectedChannelId: string | null;
    currentUserId: number;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'border-border/70 flex min-h-0 w-full flex-1 flex-col overflow-hidden border-b xl:w-[330px] xl:flex-none xl:border-r xl:border-b-0',
                className,
            )}
        >
            <div className="px-4 pt-4 pb-3">
                <h1 className="mb-3 text-lg font-semibold">Atendimento</h1>

                <form onSubmit={onSubmitSearch} className="relative">
                    <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                    <Input
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder="Buscar por nome ou telefone…"
                        className="bg-muted/40 h-8 pl-8 text-xs"
                        aria-label="Buscar por nome ou telefone"
                    />
                </form>

                <div className="mt-2.5 flex gap-1">
                    <QueuePill
                        active={filters.queue === 'awaiting'}
                        count={counts.awaiting}
                        onClick={() => onUpdateFilters({ queue: 'awaiting' })}
                    >
                        Aguardando
                    </QueuePill>
                    <QueuePill
                        active={filters.queue === 'mine'}
                        count={counts.mine}
                        onClick={() => onUpdateFilters({ queue: 'mine' })}
                    >
                        Minhas
                    </QueuePill>
                    <QueuePill
                        active={filters.queue === 'all'}
                        count={counts.all}
                        onClick={() => onUpdateFilters({ queue: 'all' })}
                    >
                        Todas
                    </QueuePill>
                </div>

                <div className="mt-2 flex gap-1.5">
                    <select
                        value={filters.assignedTo?.toString() ?? ''}
                        onChange={(event) =>
                            onUpdateFilters({
                                assigned_to: event.target.value || undefined,
                            })
                        }
                        className="border-border/70 bg-background h-[30px] min-w-0 flex-1 rounded-md border px-2 text-[11.5px]"
                        aria-label="Atribuída a"
                    >
                        <option value="">Atribuída a: qualquer um</option>
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
                            onUpdateFilters({
                                channel: event.target.value || undefined,
                            })
                        }
                        className="border-border/70 bg-background h-[30px] w-28 shrink-0 rounded-md border px-2 text-[11.5px]"
                        aria-label="Canal"
                    >
                        <option value="">Todos os canais</option>
                        {channels.map((channel) => (
                            <option key={channel.id} value={channel.id}>
                                {channel.status === 'connected'
                                    ? channel.label
                                    : `${channel.label} · ${channel.statusLabel}`}
                            </option>
                        ))}
                    </select>
                </div>

                <select
                    value={filters.state}
                    onChange={(event) =>
                        onUpdateFilters({
                            state: event.target.value || undefined,
                        })
                    }
                    className="border-border/70 bg-background mt-2 h-[30px] w-full rounded-md border px-2 text-[11.5px]"
                    aria-label="Estado da conversa"
                >
                    <option value="all">Todos os estados</option>
                    <option value="agent">Agente ativo</option>
                    <option value="paused">Pausa da IA</option>
                    <option value="flow">Fluxo transferido</option>
                </select>
            </div>

            <div className="border-border/70 min-h-0 flex-1 overflow-y-auto border-t">
                {conversations.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <span className="bg-muted flex size-9 items-center justify-center rounded-full">
                            <Users className="text-muted-foreground size-4" />
                        </span>
                        <p className="text-muted-foreground text-xs">
                            Nenhuma conversa corresponde a esta fila.
                        </p>
                    </div>
                ) : (
                    conversations.map((conversation) => {
                        const isActive =
                            conversation.contactId === selectedContactId &&
                            conversation.channel.id === selectedChannelId;
                        const hasUnread = conversation.unreadCount > 0;

                        return (
                            <Link
                                key={`${conversation.contactId}:${conversation.channel.id}`}
                                href={inboxShow({
                                    contact: conversation.contactId,
                                    channel: conversation.channel.id,
                                })}
                                className={cn(
                                    'border-border/70 block border-b px-4 py-3 transition-colors',
                                    isActive
                                        ? 'bg-muted border-l-foreground border-l-2 pl-[14px]'
                                        : 'hover:bg-muted/40',
                                )}
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <span className="truncate text-[13px] font-semibold">
                                        {conversation.name ??
                                            conversation.phone}
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 text-[10.5px]',
                                            hasUnread
                                                ? 'text-signal-live font-semibold'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {formatWaiting(
                                            conversation.waitingSeconds,
                                        )}
                                    </span>
                                </div>
                                <div className="mt-1 flex items-center justify-between gap-2">
                                    <p
                                        className={cn(
                                            'truncate text-[11.5px]',
                                            hasUnread
                                                ? 'text-foreground font-semibold'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {conversation.excerpt}
                                    </p>
                                    {hasUnread && (
                                        <span
                                            className="bg-signal-live text-background flex h-[18px] min-w-[18px] shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold"
                                            aria-label={`${conversation.unreadCount} ${conversation.unreadCount === 1 ? 'mensagem não lida' : 'mensagens não lidas'}`}
                                        >
                                            {conversation.unreadCount > 99
                                                ? '99+'
                                                : conversation.unreadCount}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    {conversation.state === 'agent' ? (
                                        <span className="bg-signal-live/10 text-signal-live rounded-md px-1.5 py-0.5 text-[10px] font-semibold">
                                            {conversation.agentPaused
                                                ? 'Pausa da IA'
                                                : 'Agente ativo'}
                                        </span>
                                    ) : (
                                        <>
                                            <span className="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-[10px] font-semibold">
                                                Fluxo transferido
                                            </span>
                                            {conversation.reason !== null && (
                                                <span
                                                    className={cn(
                                                        'rounded-md px-1.5 py-0.5 text-[10px] font-semibold',
                                                        reasonBadgeClass(
                                                            conversation.reason
                                                                .code,
                                                        ),
                                                    )}
                                                >
                                                    {conversation.reason.label}
                                                </span>
                                            )}
                                        </>
                                    )}
                                    {conversation.state === 'flow' &&
                                        conversation.assignee === null && (
                                            <span className="border-border text-muted-foreground rounded-md border px-1.5 py-0.5 text-[10px] font-semibold">
                                                não atribuída
                                            </span>
                                        )}
                                    {conversation.assignee !== null && (
                                        <span className="bg-muted inline-flex items-center gap-1 rounded-full py-0.5 pr-2 pl-0.5 text-[10px] font-semibold">
                                            <span className="bg-border flex size-3.5 items-center justify-center rounded-full text-[8px]">
                                                {initials(
                                                    conversation.assignee.name,
                                                )}
                                            </span>
                                            {conversation.assignee.id ===
                                            currentUserId
                                                ? 'você assumiu'
                                                : `${conversation.assignee.name} assumiu`}
                                        </span>
                                    )}
                                    {conversation.channel !== null && (
                                        <span
                                            className={cn(
                                                'ml-auto text-[10.5px]',
                                                conversation.channel.status ===
                                                    'connected'
                                                    ? 'text-muted-foreground'
                                                    : 'text-destructive font-semibold',
                                            )}
                                        >
                                            {conversation.channel.status ===
                                            'connected'
                                                ? conversation.channel.label
                                                : `${conversation.channel.label} · ${conversation.channel.statusLabel}`}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        );
                    })
                )}
            </div>
        </section>
    );
}

function QueuePill({
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
            className={cn(
                'h-[29px] flex-1 rounded-md text-[11.5px] font-semibold transition-colors',
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground hover:text-foreground',
            )}
        >
            {children} {count}
        </button>
    );
}

function EmptySelection() {
    return (
        <section className="hidden flex-1 flex-col items-center justify-center gap-3 p-8 text-center xl:flex">
            <span className="bg-muted flex size-12 items-center justify-center rounded-full">
                <Users className="text-muted-foreground size-5" />
            </span>
            <div>
                <h2 className="font-semibold">Escolha uma conversa</h2>
                <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                    Acompanhe o atendimento do Agente ou abra uma transferência
                    de Fluxo para continuar.
                </p>
            </div>
        </section>
    );
}

function SelectedConversationView({
    selected,
    currentUserId,
}: {
    selected: SelectedConversation;
    currentUserId: number;
}) {
    const [showDetails, setShowDetails] = useState(false);
    const [messageType, setMessageType] = useState<MessageType>('text');

    return (
        <>
            <ConversationThread
                selected={selected}
                currentUserId={currentUserId}
                showDetails={showDetails}
                onToggleDetails={() => setShowDetails((value) => !value)}
                messageType={messageType}
                onMessageTypeChange={setMessageType}
            />
            {showDetails && <ContactPanel selected={selected} />}
        </>
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
    messageType: MessageType;
    onMessageTypeChange: (type: MessageType) => void;
}) {
    const form = useForm({
        type: messageType,
        content: '',
        template: '',
        parameters: '',
        media: null as File | null,
    });
    const mediaInputRef = useRef<HTMLInputElement | null>(null);
    const messageScrollRef = useRef<HTMLDivElement | null>(null);
    const shouldFollowLatestRef = useRef(true);
    const hasPlacedInitialScrollRef = useRef(false);
    const latestMessageId = selected.messages.data.at(-1)?.id ?? 'empty';
    const latestMessageAnchorRef = useCallback(
        (anchor: HTMLDivElement | null) => {
            if (anchor === null || !shouldFollowLatestRef.current) {
                return;
            }

            const isInitialPlacement = !hasPlacedInitialScrollRef.current;
            hasPlacedInitialScrollRef.current = true;

            window.requestAnimationFrame(() => {
                const messageScroll = messageScrollRef.current;

                if (
                    messageScroll === null ||
                    !anchor.isConnected ||
                    (!isInitialPlacement && !shouldFollowLatestRef.current)
                ) {
                    return;
                }

                const scrollBounds = messageScroll.getBoundingClientRect();
                const anchorBounds = anchor.getBoundingClientRect();
                const top = Math.max(
                    0,
                    messageScroll.scrollTop +
                        anchorBounds.bottom -
                        scrollBounds.bottom,
                );
                const prefersReducedMotion = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;

                if (isInitialPlacement || prefersReducedMotion) {
                    messageScroll.scrollTop = top;
                    return;
                }

                messageScroll.scrollTo({ top, behavior: 'smooth' });
            });
        },
        [],
    );
    const assignedToMe = selected.assignee?.id === currentUserId;
    const selectedTemplate = selected.templates.find(
        (template) => template.name === form.data.template,
    );
    const templateMediaType =
        selectedTemplate?.headerType === 'IMAGE'
            ? 'image'
            : selectedTemplate?.headerType === 'DOCUMENT'
              ? 'document'
              : null;
    const isVideoTemplate = selectedTemplate?.headerType === 'VIDEO';

    const sendMessage = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            type: messageType,
            parameters:
                messageType === 'template'
                    ? data.parameters
                          .split('\n')
                          .map((parameter) => parameter.trim())
                          .filter(Boolean)
                    : [],
        }));
        form.post(
            messagesStore({
                contact: selected.contact.id,
                channel: selected.channel.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset('content', 'template', 'parameters', 'media');
                    if (mediaInputRef.current !== null) {
                        mediaInputRef.current.value = '';
                    }
                },
            },
        );
    };

    const selectMessageType = (type: MessageType) => {
        onMessageTypeChange(type);
        form.clearErrors('media');

        if (type !== messageType) {
            form.setData('media', null);
            if (mediaInputRef.current !== null) {
                mediaInputRef.current.value = '';
            }
        }
    };

    const selectMediaFile = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.currentTarget.files?.[0] ?? null;

        if (file === null) {
            form.setData('media', null);
            return;
        }

        const selectedMediaType =
            messageType === 'template'
                ? templateMediaType
                : isOutgoingMediaType(messageType)
                  ? messageType
                  : null;
        if (selectedMediaType === null) {
            form.setData('media', null);
            return;
        }

        const error = outgoingMediaValidationMessage(file, selectedMediaType);
        if (error !== null) {
            form.setData('media', null);
            form.setError('media', error);
            event.currentTarget.value = '';
            return;
        }

        form.clearErrors('media');
        form.setData('media', file);
    };

    const selectOutgoingAudio = (file: File | null) => {
        if (file === null) {
            form.setData('media', null);
            form.clearErrors('media');

            return;
        }

        const error = outgoingMediaValidationMessage(file, 'audio');
        if (error !== null) {
            form.setData('media', null);
            form.setError('media', error);

            return;
        }

        onMessageTypeChange('audio');
        form.setData((data) => ({ ...data, type: 'audio', media: file }));
        form.clearErrors('media');
    };

    const takeConversation = () => {
        router.post(
            take({
                contact: selected.contact.id,
                channel: selected.channel.id,
            }).url,
            undefined,
            {
                preserveScroll: true,
            },
        );
    };

    const returnConversation = () => {
        router.post(
            returnMethod({
                contact: selected.contact.id,
                channel: selected.channel.id,
            }).url,
        );
    };

    const pauseAgentConversation = () => {
        router.post(
            pauseAgent({
                contact: selected.contact.id,
                channel: selected.channel.id,
            }).url,
            undefined,
            { preserveScroll: true },
        );
    };

    const takeOverAgentConversation = () => {
        router.post(
            takeOverAgent({
                contact: selected.contact.id,
                channel: selected.channel.id,
            }).url,
            undefined,
            { preserveScroll: true },
        );
    };

    return (
        <section className="flex min-h-0 min-w-0 flex-1 flex-col">
            <header className="border-border/70 flex h-[60px] shrink-0 items-center gap-3 border-b px-5">
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    className="-ml-1 xl:hidden"
                >
                    <Link href={inboxIndex()} aria-label="Voltar para a lista">
                        <ArrowLeft />
                    </Link>
                </Button>
                <div className="bg-muted flex size-[34px] shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                    {initials(selected.contact.name)}
                </div>
                <div className="min-w-0">
                    <div className="truncate text-sm font-semibold">
                        {selected.contact.name ?? selected.contact.phone}
                    </div>
                    <div className="text-muted-foreground truncate text-[11.5px]">
                        {selected.contact.name !== null
                            ? `${selected.contact.phone} · ${selected.channel.label}`
                            : selected.channel.label}
                    </div>
                </div>
                {!selected.channel.available && (
                    <span className="text-destructive hidden text-[11px] font-semibold sm:block">
                        {selected.channel.statusLabel}
                    </span>
                )}
                <div className="flex-1" />
                <span className="text-muted-foreground bg-muted hidden items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium sm:flex">
                    <Bot className="size-3.5" />
                    {selected.state === 'flow'
                        ? 'Fluxo transferido'
                        : selected.agentPaused
                          ? 'Pausa da IA'
                          : 'Agente ativo'}
                </span>
                {selected.canPause && (
                    <Button size="sm" onClick={pauseAgentConversation}>
                        Pausar Agente e assumir
                    </Button>
                )}
                {selected.canTakeOver && (
                    <Button size="sm" onClick={takeOverAgentConversation}>
                        Assumir conversa
                    </Button>
                )}
                {selected.state === 'flow' && selected.assignee === null && (
                    <Button size="sm" onClick={takeConversation}>
                        Assumir conversa
                    </Button>
                )}
                {selected.assignee !== null && (
                    <span className="text-muted-foreground hidden items-center gap-1.5 text-xs md:flex">
                        <UserRound className="size-3.5" />
                        {assignedToMe
                            ? 'Você assumiu'
                            : `${selected.assignee.name} assumiu`}
                    </span>
                )}
                <button
                    type="button"
                    onClick={onToggleDetails}
                    aria-label={
                        showDetails
                            ? 'Fechar o painel do contato'
                            : 'Abrir o painel do contato'
                    }
                    aria-pressed={showDetails}
                    className={cn(
                        'border-border/70 flex size-[33px] shrink-0 items-center justify-center rounded-md border',
                        showDetails ? 'bg-muted' : 'bg-background',
                    )}
                >
                    {showDetails ? (
                        <PanelRightClose className="size-4" />
                    ) : (
                        <PanelRight className="size-4" />
                    )}
                </button>
            </header>

            <div
                ref={messageScrollRef}
                onScroll={(event) => {
                    const { clientHeight, scrollHeight, scrollTop } =
                        event.currentTarget;
                    shouldFollowLatestRef.current =
                        scrollHeight - scrollTop - clientHeight <= 96;
                }}
                className="bg-muted/20 min-h-0 flex-1 space-y-3 overflow-y-auto p-5"
            >
                <div className="flex justify-center">
                    <span className="border-border/70 bg-background text-muted-foreground flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-medium">
                        <Bot className="text-signal-attention size-3.5" />
                        {selected.state === 'flow' &&
                        selected.execution !== null
                            ? `O Fluxo ${selected.execution.flowName} transferiu esta conversa para atendimento humano`
                            : selected.agentPaused
                              ? 'O Agente está pausado nesta conversa'
                              : 'O Agente está conduzindo esta conversa'}
                    </span>
                </div>

                {selected.messages.data.map((message) => (
                    <MessageBubble key={message.id} message={message} />
                ))}

                <div
                    key={latestMessageId}
                    ref={latestMessageAnchorRef}
                    className="h-px"
                    aria-hidden="true"
                />

                <HistoryPanel history={selected.history} />
            </div>

            {selected.state === 'flow' || selected.canWrite ? (
                <div className="border-border/70 bg-background shrink-0 border-t p-4">
                    {!selected.channel.available && (
                        <div className="border-destructive/40 bg-destructive/10 text-foreground mb-3 rounded-lg border px-3 py-2 text-sm">
                            Envio bloqueado: Canal{' '}
                            {selected.channel.statusLabel.toLowerCase()}.
                        </div>
                    )}
                    {selected.channel.available && !selected.canWrite && (
                        <div className="border-signal-attention/40 bg-signal-attention/10 text-foreground mb-3 rounded-lg border px-3 py-2 text-sm">
                            {selected.assignee === null
                                ? 'Assuma a conversa antes de escrever.'
                                : `${selected.assignee.name} está atendendo esta conversa. Você precisa assumir antes de escrever.`}
                        </div>
                    )}
                    {selected.state === 'agent' && !selected.agentPaused && (
                        <div className="border-signal-attention/40 bg-signal-attention/10 text-foreground mb-3 rounded-lg border px-3 py-2 text-sm">
                            Enviar uma Mensagem pausará o Agente e atribuirá a
                            conversa a você.
                        </div>
                    )}
                    <form onSubmit={sendMessage} className="grid gap-2">
                        <div className="flex flex-wrap gap-1">
                            <button
                                type="button"
                                onClick={() => selectMessageType('text')}
                                className={cn(
                                    'rounded px-2 py-1 text-xs font-medium',
                                    messageType === 'text'
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                Resposta livre
                            </button>
                            <button
                                type="button"
                                onClick={() => selectMessageType('template')}
                                className={cn(
                                    'rounded px-2 py-1 text-xs font-medium',
                                    messageType === 'template'
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                Template
                            </button>
                            {(
                                ['image', 'document', 'video', 'audio'] as const
                            ).map((type) => {
                                const Icon = {
                                    image: ImagePlus,
                                    document: FileText,
                                    video: Video,
                                    audio: Mic,
                                }[type];

                                return (
                                    <button
                                        key={type}
                                        type="button"
                                        onClick={() => selectMessageType(type)}
                                        aria-pressed={messageType === type}
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium',
                                            messageType === type
                                                ? 'bg-muted text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <Icon className="size-3.5" />
                                        {outgoingMediaOptions[type].label}
                                    </button>
                                );
                            })}
                        </div>
                        {messageType === 'template' ? (
                            <>
                                <Input
                                    value={form.data.template}
                                    list="approved-whatsapp-templates"
                                    onChange={(event) => {
                                        form.setData((data) => ({
                                            ...data,
                                            template: event.target.value,
                                            media: null,
                                        }));
                                        form.clearErrors('media');
                                        if (mediaInputRef.current !== null) {
                                            mediaInputRef.current.value = '';
                                        }
                                    }}
                                    placeholder="Nome do Template aprovado"
                                    disabled={
                                        !selected.canSend || form.processing
                                    }
                                />
                                <datalist id="approved-whatsapp-templates">
                                    {selected.templates.map((template) => (
                                        <option
                                            key={template.name}
                                            value={template.name}
                                        >
                                            {template.language}
                                        </option>
                                    ))}
                                </datalist>
                                {isVideoTemplate && (
                                    <p
                                        className="text-signal-attention text-xs"
                                        role="status"
                                    >
                                        Templates com cabeçalho de vídeo ainda
                                        não podem ser enviados por aqui.
                                    </p>
                                )}
                                {templateMediaType !== null && (
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="inbox-template-media"
                                            className="text-muted-foreground text-xs"
                                        >
                                            {
                                                outgoingMediaOptions[
                                                    templateMediaType
                                                ].label
                                            }{' '}
                                            · até{' '}
                                            {
                                                outgoingMediaOptions[
                                                    templateMediaType
                                                ].maxLabel
                                            }
                                        </label>
                                        <input
                                            ref={mediaInputRef}
                                            id="inbox-template-media"
                                            type="file"
                                            accept={
                                                outgoingMediaOptions[
                                                    templateMediaType
                                                ].accept
                                            }
                                            onChange={selectMediaFile}
                                            required
                                            disabled={
                                                !selected.canSend ||
                                                form.processing
                                            }
                                            className="border-border/70 file:bg-muted file:text-foreground rounded-lg border px-3 py-2 text-xs file:mr-3 file:rounded file:border-0 file:px-2 file:py-1"
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            {form.data.media?.name ??
                                                'Selecione o arquivo para este Contato.'}
                                        </span>
                                    </div>
                                )}
                                <textarea
                                    value={form.data.parameters}
                                    onChange={(event) =>
                                        form.setData(
                                            'parameters',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Um parâmetro por linha (opcional)"
                                    className="border-border/70 placeholder:text-muted-foreground min-h-[60px] w-full resize-y rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus-visible:ring-2"
                                    disabled={
                                        !selected.canSend || form.processing
                                    }
                                />
                            </>
                        ) : messageType === 'text' ? (
                            <textarea
                                value={form.data.content}
                                onChange={(event) =>
                                    form.setData('content', event.target.value)
                                }
                                placeholder={
                                    selected.canWrite
                                        ? 'Escreva uma resposta…'
                                        : 'Assuma a conversa para responder…'
                                }
                                className="border-border/70 placeholder:text-muted-foreground min-h-[60px] w-full resize-y rounded-lg border bg-transparent px-3 py-2.5 text-sm outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
                                disabled={
                                    !selected.canSend ||
                                    !selected.window.open ||
                                    form.processing
                                }
                            />
                        ) : messageType === 'audio' ? (
                            <VoiceMessageRecorder
                                key={`${selected.contact.id}:${selected.channel.id}`}
                                disabled={
                                    !selected.canSend ||
                                    !selected.window.open ||
                                    form.processing
                                }
                                file={form.data.media}
                                error={form.errors.media}
                                onFileChange={selectOutgoingAudio}
                            />
                        ) : (
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="inbox-media"
                                    className="text-muted-foreground text-xs"
                                >
                                    {outgoingMediaOptions[messageType].label} ·
                                    até{' '}
                                    {outgoingMediaOptions[messageType].maxLabel}
                                </label>
                                <input
                                    ref={mediaInputRef}
                                    id="inbox-media"
                                    type="file"
                                    accept={
                                        outgoingMediaOptions[messageType].accept
                                    }
                                    onChange={selectMediaFile}
                                    disabled={
                                        !selected.canSend ||
                                        !selected.window.open ||
                                        form.processing
                                    }
                                    className="border-border/70 file:bg-muted file:text-foreground rounded-lg border px-3 py-2 text-xs file:mr-3 file:rounded file:border-0 file:px-2 file:py-1"
                                />
                                <span className="text-muted-foreground text-xs">
                                    {form.data.media?.name ??
                                        'Selecione um arquivo para anexar.'}
                                </span>
                            </div>
                        )}
                        {form.errors.content && (
                            <p className="text-destructive text-xs">
                                {form.errors.content}
                            </p>
                        )}
                        {form.errors.media && (
                            <p
                                className="text-destructive text-xs"
                                role="alert"
                            >
                                {form.errors.media}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center gap-3">
                            {selected.window.open ? (
                                <span className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                    <Clock3 className="text-signal-live size-3.5" />
                                    Janela de 24 h aberta ·{' '}
                                    {formatRemaining(
                                        selected.window.remainingSeconds,
                                    )}
                                </span>
                            ) : (
                                <span className="text-destructive flex items-center gap-1.5 text-[11px] font-medium">
                                    <Clock3 className="size-3.5" />
                                    Janela de 24 h encerrada · somente Template
                                </span>
                            )}
                            <span className="flex-1" />
                            {selected.state === 'flow' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={returnConversation}
                                >
                                    Devolver ao bot
                                </Button>
                            )}
                            {selected.canReturn && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={returnConversation}
                                >
                                    Devolver ao Agente
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    !selected.canSend ||
                                    form.processing ||
                                    isVideoTemplate ||
                                    (templateMediaType !== null &&
                                        form.data.media === null) ||
                                    (messageType !== 'template' &&
                                        !selected.window.open)
                                }
                            >
                                <Send />
                                Enviar
                            </Button>
                        </div>
                    </form>
                </div>
            ) : (
                <div className="border-border/70 bg-background text-muted-foreground shrink-0 border-t px-4 py-3 text-center text-xs">
                    {selected.agentPaused
                        ? `Agente pausado${selected.agentPauseExpiresAt ? ` até ${formatDate(selected.agentPauseExpiresAt)}` : ''}. Envio humano disponível em breve.`
                        : 'Conversa em modo de leitura enquanto o Agente atende.'}
                </div>
            )}
        </section>
    );
}

function MessageBubble({ message }: { message: Message }) {
    const outgoing = message.direction === 'outgoing';
    const [mediaUnavailable, setMediaUnavailable] = useState(false);
    const unsupported = isUnsupportedMessage(message);
    let content = message.content;
    if (message.payloadType === 'template' && content !== null) {
        try {
            const template = JSON.parse(content) as { template?: string };
            content = template.template
                ? `Template aprovado: ${template.template}`
                : 'Template aprovado';
        } catch {
            content = 'Template aprovado';
        }
    }
    const mediaType = ['image', 'audio', 'video', 'document'].includes(
        message.payloadType,
    )
        ? message.payloadType
        : message.payloadType === 'template' && message.mediaAvailable
          ? message.mediaMimeType?.startsWith('image/')
              ? 'image'
              : 'document'
          : null;
    const hasMedia = mediaType !== null;

    return (
        <div className={cn('flex', outgoing ? 'justify-end' : 'justify-start')}>
            <div
                className={cn(
                    'relative max-w-[62%] px-3.5 py-2.5 text-[13px] leading-relaxed',
                    outgoing
                        ? 'bg-primary text-primary-foreground rounded-2xl rounded-br-sm'
                        : 'bg-background border-border/70 rounded-2xl rounded-bl-sm border',
                )}
            >
                {message.reactionEmoji !== null && (
                    <span
                        role="img"
                        aria-label={`Reação: ${message.reactionEmoji}`}
                        className="border-border/70 bg-background absolute -top-3 right-2 z-10 grid size-7 place-items-center rounded-full border px-1 text-sm leading-none shadow-sm"
                    >
                        {message.reactionEmoji}
                    </span>
                )}
                {mediaType !== null &&
                message.mediaUrl !== null &&
                !mediaUnavailable ? (
                    <div className="mb-2.5">
                        {mediaType === 'image' && (
                            <a
                                href={message.mediaUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={message.mediaUrl}
                                    alt={
                                        message.mediaFilename ??
                                        'Imagem da mensagem'
                                    }
                                    className="max-h-72 max-w-full rounded-lg object-contain"
                                    onError={() => setMediaUnavailable(true)}
                                />
                            </a>
                        )}
                        {mediaType === 'audio' && (
                            <audio
                                controls
                                preload="metadata"
                                className="max-w-full"
                                onError={() => setMediaUnavailable(true)}
                            >
                                <source
                                    src={message.mediaUrl}
                                    type={message.mediaMimeType ?? undefined}
                                />
                                Seu navegador não conseguiu reproduzir este
                                áudio.
                            </audio>
                        )}
                        {mediaType === 'video' && (
                            <video
                                controls
                                preload="metadata"
                                className="max-h-80 max-w-full rounded-lg"
                                onError={() => setMediaUnavailable(true)}
                            >
                                <source
                                    src={message.mediaUrl}
                                    type={message.mediaMimeType ?? undefined}
                                />
                                Seu navegador não conseguiu reproduzir este
                                vídeo.
                            </video>
                        )}
                        {mediaType === 'document' && (
                            <a
                                href={message.mediaUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary underline underline-offset-2"
                            >
                                {message.mediaMimeType === 'application/pdf'
                                    ? `Abrir ${message.mediaFilename ?? 'documento'}`
                                    : `Baixar ${message.mediaFilename ?? 'documento'}`}
                            </a>
                        )}
                    </div>
                ) : null}
                {hasMedia &&
                    (!message.mediaAvailable ||
                        message.mediaUrl === null ||
                        mediaUnavailable) && (
                        <p className="text-muted-foreground mb-2 text-xs italic">
                            Mídia original indisponível
                        </p>
                    )}
                <p className="break-words whitespace-pre-wrap">
                    {unsupported
                        ? 'Tipo de mensagem não suportado'
                        : (content ?? `[${message.payloadType}]`)}
                </p>
                {message.deliveryStatus === 'failed' && (
                    <span className="text-destructive mt-1 block text-[10px] font-semibold">
                        {failureReasonLabel(message.failureReason)}
                    </span>
                )}
                <span
                    className={cn(
                        'mt-1.5 block text-[10px]',
                        outgoing
                            ? 'text-primary-foreground/60'
                            : 'text-muted-foreground',
                    )}
                >
                    {message.sender} · {formatDate(message.createdAt)}
                    {outgoing && message.deliveryStatus !== null && (
                        <>
                            {' '}
                            ·{' '}
                            {deliveryStatusLabels[message.deliveryStatus] ??
                                'Enviada'}
                        </>
                    )}
                </span>
            </div>
        </div>
    );
}

function HistoryPanel({ history }: { history: MessagePage }) {
    return (
        <details className="border-border/70 bg-background overflow-hidden rounded-lg border">
            <summary className="hover:bg-muted/40 cursor-pointer px-4 py-2.5 text-[12.5px] font-medium">
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
                            {isUnsupportedMessage(message)
                                ? 'Tipo de mensagem não suportado'
                                : (message.content ??
                                  `[${message.payloadType}]`)}
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
        <aside className="border-border/70 flex max-h-[35%] min-h-0 w-full shrink-0 flex-col overflow-y-auto border-t xl:max-h-none xl:w-[300px] xl:border-t-0 xl:border-l">
            <div className="border-border/70 flex items-start gap-2.5 border-b px-4.5 py-4">
                <div className="min-w-0 flex-1">
                    <span className="text-muted-foreground block text-[10px] font-bold tracking-[0.07em] uppercase">
                        Contato
                    </span>
                    <span className="mt-1 block text-[15px] font-semibold">
                        {selected.contact.name ?? selected.contact.phone}
                    </span>
                </div>
            </div>

            {selected.contact.tags.length > 0 && (
                <div className="border-border/70 border-b px-4.5 py-3.5">
                    <div className="flex flex-wrap gap-1.5">
                        {selected.contact.tags.map((tag) => (
                            <Badge
                                key={tag.id}
                                variant="secondary"
                                className="text-[10.5px]"
                            >
                                {tag.name}
                            </Badge>
                        ))}
                    </div>
                </div>
            )}

            <div className="border-border/70 border-b px-4.5 py-3.5">
                <div className="text-muted-foreground mb-2.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                    Campos do contato
                </div>
                <div className="grid gap-2">
                    <ContactField label="Telefone">
                        <span className="font-mono">
                            {selected.contact.phone}
                        </span>
                    </ContactField>
                    {selected.contact.fields.map((field) => (
                        <ContactField key={field.name} label={field.name}>
                            <span
                                className={
                                    field.sensitive ? 'font-mono' : undefined
                                }
                            >
                                {field.value ?? '—'}
                            </span>
                        </ContactField>
                    ))}
                </div>
            </div>

            <div className="border-border/70 border-b px-4.5 py-3.5">
                <div className="text-muted-foreground mb-2.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                    Consentimentos
                </div>
                <div className="grid gap-2">
                    {Object.values(selected.contact.consents).map((consent) => (
                        <div
                            key={consent.purpose}
                            className="flex items-center justify-between gap-3 text-xs"
                        >
                            <span>{consent.label}</span>
                            <Badge
                                variant={
                                    consent.granted ? 'secondary' : 'outline'
                                }
                                className="text-[10px]"
                            >
                                {consent.granted ? 'Ativo' : 'Não dado'}
                            </Badge>
                        </div>
                    ))}
                </div>
            </div>

            {selected.execution !== null ? (
                <div className="px-4.5 py-3.5">
                    <div className="text-muted-foreground mb-2.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                        Execução pausada
                    </div>
                    <div className="border-border/70 rounded-lg border p-3">
                        <div className="text-[12.5px] font-semibold">
                            {selected.execution.flowName}
                        </div>
                        <div className="text-muted-foreground mt-0.5 text-[11px]">
                            #{selected.execution.id} · v
                            {selected.execution.version}
                            {' · '}
                            {selected.execution.nodeName}
                        </div>
                        <div className="bg-muted mt-2 flex items-center gap-1.5 rounded-md px-2.5 py-1.5">
                            <span className="bg-signal-attention size-1.5 shrink-0 rounded-full" />
                            <span className="text-[11.5px] font-medium">
                                {selected.reason?.label ??
                                    'Atendimento transferido'}
                            </span>
                        </div>
                        <Link
                            href={selected.execution.traceUrl}
                            className="mt-2.5 inline-block text-[11.5px] font-semibold underline underline-offset-4"
                        >
                            Ver o traço completo →
                        </Link>
                    </div>
                </div>
            ) : (
                <div className="px-4.5 py-3.5">
                    <div className="text-muted-foreground mb-2.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                        Atendimento
                    </div>
                    <div className="border-border/70 rounded-lg border p-3 text-xs">
                        {selected.agentPaused
                            ? 'Pausa da IA ativa neste Canal.'
                            : 'Agente ativo neste Canal.'}
                    </div>
                </div>
            )}
        </aside>
    );
}

function ContactField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <span className="text-muted-foreground shrink-0 text-xs">
                {label}
            </span>
            <span className="truncate text-right text-xs font-medium">
                {children}
            </span>
        </div>
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

function reasonBadgeClass(code: string): string {
    switch (code) {
        case 'contact_requested':
            return 'bg-destructive/10 text-destructive';
        case 'unsupported_media':
            return 'bg-signal-attention/15 text-signal-attention';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

function initials(name: string | null): string {
    if (name === null || name.trim() === '') return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

function formatWaiting(seconds: number): string {
    if (seconds < 60) return 'agora';
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)} min`;
    return `há ${Math.floor(seconds / 3600)} h`;
}

function formatRemaining(seconds: number | null): string {
    if (seconds === null) return 'aberta';
    if (seconds <= 0) return 'encerrada';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return hours > 0
        ? `fecha em ${hours} h ${minutes} min`
        : `fecha em ${minutes} min`;
}

function formatDate(value: string | null): string {
    if (value === null) return '';
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
