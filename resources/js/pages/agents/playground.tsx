import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Bot,
    BookOpen,
    CheckCircle2,
    CircleAlert,
    RotateCcw,
    Send,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/agents';
import { stream } from '@/routes/agents/playground';

type Agent = {
    id: number;
    name: string;
    objectiveLabel: string;
    welcomeMessage: string;
    collectionCount: number;
    isActive: boolean;
};

type Source = {
    document: { id: number; title: string };
    collection: { id: number; name: string };
    content: string;
    similarity: number;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    sources: Source[];
    baseConsulted: boolean;
    isError?: boolean;
    isStreaming?: boolean;
};

type Props = {
    agent: Agent;
};

type StreamEvent = {
    type?: string;
    delta?: string;
    tool_name?: string;
    result?: unknown;
    message?: string;
};

const friendlyError =
    'Não foi possível obter uma resposta agora. Tente novamente em instantes.';

function createWelcomeMessage(agent: Agent): ChatMessage {
    return {
        id: 'welcome',
        role: 'assistant',
        content: agent.welcomeMessage,
        sources: [],
        baseConsulted: false,
    };
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function parseSources(result: unknown): Source[] {
    const parsed =
        typeof result === 'string'
            ? (() => {
                  try {
                      return JSON.parse(result) as unknown;
                  } catch {
                      return null;
                  }
              })()
            : result;

    if (!isRecord(parsed) || !Array.isArray(parsed.results)) {
        return [];
    }

    return parsed.results.filter((source): source is Source => {
        return (
            isRecord(source) &&
            isRecord(source.document) &&
            isRecord(source.collection) &&
            typeof source.content === 'string' &&
            typeof source.similarity === 'number'
        );
    });
}

function sourceKey(source: Source): string {
    return `${source.document.id}-${source.collection.id}-${source.content}`;
}

export default function Playground({ agent }: Props) {
    const [messages, setMessages] = useState<ChatMessage[]>([
        createWelcomeMessage(agent),
    ]);
    const [draft, setDraft] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const [selectedMessageId, setSelectedMessageId] = useState('welcome');
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const abortController = useRef<AbortController | null>(null);
    const messagesEnd = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        setHasUnsavedChanges(
            window.localStorage.getItem(
                `agent-playground-dirty-${agent.id}`,
            ) === 'true',
        );
    }, [agent.id]);

    useEffect(() => {
        messagesEnd.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const selectedMessage =
        messages.find((message) => message.id === selectedMessageId) ??
        messages[messages.length - 1];

    const updateAssistantMessage = (
        assistantId: string,
        update: (message: ChatMessage) => ChatMessage,
    ) => {
        setMessages((current) =>
            current.map((message) =>
                message.id === assistantId ? update(message) : message,
            ),
        );
    };

    const processEvent = (assistantId: string, event: StreamEvent) => {
        if (event.type === 'text_delta' && typeof event.delta === 'string') {
            updateAssistantMessage(assistantId, (message) => ({
                ...message,
                content: message.content + event.delta,
            }));
        }

        if (
            event.type === 'tool_result' &&
            event.tool_name === 'search_knowledge_base'
        ) {
            const sources = parseSources(event.result);

            updateAssistantMessage(assistantId, (message) => {
                const sourceMap = new Map(
                    [...message.sources, ...sources].map((source) => [
                        sourceKey(source),
                        source,
                    ]),
                );

                return {
                    ...message,
                    baseConsulted: true,
                    sources: [...sourceMap.values()],
                };
            });
        }

        if (event.type === 'error') {
            updateAssistantMessage(assistantId, (message) => ({
                ...message,
                content: event.message ?? friendlyError,
                isError: true,
                isStreaming: false,
            }));
        }

        if (event.type === 'stream_end') {
            updateAssistantMessage(assistantId, (message) => ({
                ...message,
                isStreaming: false,
            }));
        }
    };

    const sendMessage = async () => {
        const message = draft.trim();

        if (message === '' || isStreaming) {
            return;
        }

        const assistantId = `assistant-${crypto.randomUUID()}`;
        const userMessage: ChatMessage = {
            id: `user-${crypto.randomUUID()}`,
            role: 'user',
            content: message,
            sources: [],
            baseConsulted: false,
        };
        const assistantMessage: ChatMessage = {
            id: assistantId,
            role: 'assistant',
            content: '',
            sources: [],
            baseConsulted: false,
            isStreaming: true,
        };
        const history = messages
            .filter((item) => item.id !== 'welcome')
            .map(({ role, content }) => ({ role, content }));

        setMessages((current) => [...current, userMessage, assistantMessage]);
        setSelectedMessageId(assistantId);
        setDraft('');
        setIsStreaming(true);

        const controller = new AbortController();
        abortController.current = controller;

        try {
            const response = await fetch(stream(agent.id).url, {
                method: 'POST',
                headers: {
                    Accept: 'text/event-stream, application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ history, message }),
                signal: controller.signal,
            });

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                throw new Error(body?.message ?? friendlyError);
            }

            if (!response.body) {
                throw new Error(friendlyError);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                buffer += decoder.decode(value, { stream: !done });

                const blocks = buffer.split('\n\n');
                buffer = blocks.pop() ?? '';

                for (const block of blocks) {
                    const data = block
                        .split('\n')
                        .find((line) => line.startsWith('data: '))
                        ?.slice(6);

                    if (!data || data === '[DONE]') {
                        continue;
                    }

                    processEvent(assistantId, JSON.parse(data) as StreamEvent);
                }

                if (done) {
                    break;
                }
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            updateAssistantMessage(assistantId, (current) => ({
                ...current,
                content: error instanceof Error ? error.message : friendlyError,
                isError: true,
                isStreaming: false,
            }));
        } finally {
            setIsStreaming(false);
            abortController.current = null;
        }
    };

    const resetConversation = () => {
        abortController.current?.abort();
        setMessages([createWelcomeMessage(agent)]);
        setSelectedMessageId('welcome');
        setDraft('');
        setIsStreaming(false);
    };

    return (
        <>
            <Head title={`Playground · ${agent.name}`} />

            <div className="flex min-h-0 flex-1 flex-col gap-5 p-4 md:p-8">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={index().url}
                                aria-label="Voltar para Agentes"
                            >
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <div className="bg-muted flex size-10 items-center justify-center rounded-xl">
                            <Bot className="size-5" />
                        </div>
                        <div className="grid gap-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {agent.name}
                                </h1>
                                <Badge
                                    variant={
                                        agent.isActive ? 'default' : 'secondary'
                                    }
                                >
                                    {agent.isActive ? 'Ativo' : 'Inativo'}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Playground · {agent.objectiveLabel}
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        onClick={resetConversation}
                        disabled={isStreaming}
                    >
                        <RotateCcw />
                        Reiniciar conversa
                    </Button>
                </header>

                {hasUnsavedChanges && (
                    <Alert>
                        <CircleAlert />
                        <AlertTitle>Há alterações não salvas</AlertTitle>
                        <AlertDescription>
                            Este Playground usa a última configuração salva do
                            Agente. Salve as alterações antes de testar para ver
                            o novo comportamento.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid min-h-0 flex-1 gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card className="flex min-h-[32rem] min-w-0 flex-1 flex-col gap-0 overflow-hidden py-0 shadow-none">
                        <CardHeader className="border-b py-4">
                            <CardTitle className="text-base">
                                Conversa de teste
                            </CardTitle>
                            <CardDescription>
                                Teste como {agent.name} responde usando a
                                configuração salva.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 md:p-6">
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                                {messages.map((item) => (
                                    <div
                                        className={`flex ${item.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                        key={item.id}
                                    >
                                        <div className="grid max-w-[85%] gap-2">
                                            <div
                                                className={`rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap ${item.role === 'user' ? 'bg-primary text-primary-foreground rounded-br-sm' : item.isError ? 'border-destructive/40 bg-destructive/10 text-destructive rounded-bl-sm border' : 'bg-muted rounded-bl-sm'}`}
                                            >
                                                {item.content ||
                                                    (item.isStreaming && (
                                                        <Spinner />
                                                    ))}
                                                {item.isStreaming &&
                                                    item.content !== '' && (
                                                        <span className="bg-foreground/60 ml-1 inline-block size-1.5 animate-pulse rounded-full align-middle" />
                                                    )}
                                            </div>
                                            {item.role === 'assistant' &&
                                                item.id !== 'welcome' && (
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground flex w-fit items-center gap-1 text-xs transition-colors"
                                                        onClick={() =>
                                                            setSelectedMessageId(
                                                                item.id,
                                                            )
                                                        }
                                                    >
                                                        <BookOpen className="size-3.5" />
                                                        {item.baseConsulted
                                                            ? `${item.sources.length} ${item.sources.length === 1 ? 'fonte consultada' : 'fontes consultadas'}`
                                                            : 'Base não consultada'}
                                                    </button>
                                                )}
                                        </div>
                                    </div>
                                ))}
                                <div ref={messagesEnd} />
                            </div>
                            <form
                                className="border-input bg-background focus-within:ring-ring/30 flex items-end gap-2 rounded-xl border p-2 focus-within:ring-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    void sendMessage();
                                }}
                            >
                                <textarea
                                    value={draft}
                                    onChange={(event) =>
                                        setDraft(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === 'Enter' &&
                                            !event.shiftKey
                                        ) {
                                            event.preventDefault();
                                            void sendMessage();
                                        }
                                    }}
                                    placeholder="Digite uma mensagem para testar o Agente..."
                                    aria-label="Mensagem para o Agente"
                                    className="placeholder:text-muted-foreground max-h-32 min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm outline-none"
                                    disabled={isStreaming}
                                    maxLength={4000}
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    aria-label="Enviar mensagem"
                                    disabled={
                                        isStreaming || draft.trim() === ''
                                    }
                                >
                                    {isStreaming ? <Spinner /> : <Send />}
                                </Button>
                            </form>
                            <p className="text-muted-foreground text-center text-xs">
                                Limite de 30 mensagens por hora por usuário ·
                                Enter envia · Shift + Enter quebra linha
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="min-h-0 overflow-hidden py-0 shadow-none">
                        <CardHeader className="border-b py-4">
                            <CardTitle className="text-base">
                                Fontes da resposta
                            </CardTitle>
                            <CardDescription>
                                {selectedMessage?.role === 'assistant' &&
                                selectedMessage.id !== 'welcome'
                                    ? 'Trechos consultados nesta resposta.'
                                    : 'Selecione uma resposta para ver suas fontes.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="max-h-[34rem] space-y-4 overflow-y-auto p-4">
                            {selectedMessage?.baseConsulted ? (
                                selectedMessage.sources.length > 0 ? (
                                    selectedMessage.sources.map((source) => (
                                        <div
                                            className="grid gap-2"
                                            key={sourceKey(source)}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {source.document.title}
                                                    </p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {source.collection.name}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant="outline"
                                                    className="shrink-0 tabular-nums"
                                                >
                                                    {Math.round(
                                                        source.similarity * 100,
                                                    )}
                                                    %
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground bg-muted/50 rounded-lg p-3 text-xs leading-relaxed">
                                                {source.content}
                                            </p>
                                            <Separator />
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-muted-foreground grid justify-items-center gap-2 py-10 text-center text-sm">
                                        <CheckCircle2 className="text-primary size-5" />
                                        <p>
                                            A base foi consultada, mas não há
                                            trechos relevantes para esta
                                            pergunta.
                                        </p>
                                    </div>
                                )
                            ) : (
                                <div className="text-muted-foreground grid justify-items-center gap-2 py-10 text-center text-sm">
                                    <BookOpen className="size-5" />
                                    <p>
                                        {selectedMessage?.id === 'welcome'
                                            ? 'A mensagem de boas-vindas não consulta a base.'
                                            : 'Esta resposta não consultou a base de conhecimento.'}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                        <div className="text-muted-foreground border-t px-4 py-3 text-xs">
                            {agent.collectionCount === 0
                                ? 'Este Agente usa apenas os dados da Empresa.'
                                : `${agent.collectionCount} ${agent.collectionCount === 1 ? 'Coleção disponível' : 'Coleções disponíveis'} para consulta.`}
                        </div>
                    </Card>
                </div>
            </div>
        </>
    );
}
