import { BookOpen, CheckCircle2, ChevronDown, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { stream } from '@/routes/agents/playground';

export type AgentDraft = {
    name: string;
    objective: string;
    tone: string;
    usesEmojis: boolean;
    responseLength: string;
    welcomeMessage: string;
    instructions: string[];
    rules: string[];
    collectionIds: string[];
    schedulableCalendarIds: string[];
    searchProducts: boolean;
    sendPurchaseLink: boolean;
    crmLookupEnabled: boolean;
    crmEnsureDealEnabled: boolean;
    crmPipelineId: string;
    crmInitialStageId: string;
};

type Source = {
    document: { id: number; title: string };
    collection: { id: string; name: string };
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

type StreamEvent = {
    type?: string;
    delta?: string;
    tool_name?: string;
    result?: unknown;
    message?: string;
};

type Props = {
    draft: AgentDraft;
};

const friendlyError =
    'Não foi possível obter uma resposta agora. Tente novamente em instantes.';

function createWelcomeMessage(welcomeMessage: string): ChatMessage {
    return {
        id: 'welcome',
        role: 'assistant',
        content: welcomeMessage,
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
            typeof source.document.id === 'number' &&
            typeof source.document.title === 'string' &&
            typeof source.collection.id === 'string' &&
            typeof source.collection.name === 'string' &&
            typeof source.content === 'string' &&
            typeof source.similarity === 'number'
        );
    });
}

function sourceKey(source: Source): string {
    return `${source.document.id}-${source.collection.id}-${source.content}`;
}

function messageSourcesLabel(message: ChatMessage): string {
    if (!message.baseConsulted) {
        return 'Base não consultada';
    }

    return `${message.sources.length} ${message.sources.length === 1 ? 'fonte consultada' : 'fontes consultadas'}`;
}

export default function AgentPlayground({ draft }: Props) {
    const [messages, setMessages] = useState<ChatMessage[]>([
        createWelcomeMessage(draft.welcomeMessage),
    ]);
    const [messageDraft, setMessageDraft] = useState('');
    const [crmLookupPhone, setCrmLookupPhone] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const abortController = useRef<AbortController | null>(null);
    const displayedMessages =
        messages.length === 1 && messages[0]?.id === 'welcome'
            ? [{ ...messages[0], content: draft.welcomeMessage }]
            : messages;

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
        const message = messageDraft.trim();

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
        const history = displayedMessages
            .filter((item) => item.id !== 'welcome')
            .map(({ role, content }) => ({ role, content }));

        setMessages((current) => [...current, userMessage, assistantMessage]);
        setMessageDraft('');
        setIsStreaming(true);

        const controller = new AbortController();
        abortController.current = controller;

        try {
            const response = await fetch(stream().url, {
                method: 'POST',
                headers: {
                    Accept: 'text/event-stream, application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: draft.name,
                    objective: draft.objective,
                    tone: draft.tone,
                    uses_emojis: draft.usesEmojis,
                    response_length: draft.responseLength,
                    welcome_message: draft.welcomeMessage,
                    instructions: draft.instructions,
                    rules: draft.rules,
                    search_products: draft.searchProducts,
                    send_purchase_link: draft.sendPurchaseLink,
                    collection_ids: draft.collectionIds,
                    schedulable_calendar_ids: draft.schedulableCalendarIds,
                    crm_lookup_enabled: draft.crmLookupEnabled,
                    crm_lookup_phone: crmLookupPhone || null,
                    history,
                    message,
                }),
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
        setMessages([createWelcomeMessage(draft.welcomeMessage)]);
        setMessageDraft('');
        setIsStreaming(false);
    };

    return (
        <Card className="flex min-h-[32rem] min-w-0 flex-1 flex-col gap-0 overflow-hidden py-0 shadow-none">
            <CardHeader className="border-b py-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="grid gap-1">
                        <CardTitle className="text-base">Playground</CardTitle>
                        <CardDescription>
                            Teste como {draft.name || 'este Agente'} responde
                            com a configuração atual.
                        </CardDescription>
                        {(draft.crmLookupEnabled || draft.crmEnsureDealEnabled) && (
                            <div className="mt-3 grid gap-2">
                                <Label htmlFor="playground-crm-phone">
                                    Telefone para consulta no CRM
                                </Label>
                                <Input
                                    id="playground-crm-phone"
                                    type="tel"
                                    value={crmLookupPhone}
                                    onChange={(event) =>
                                        setCrmLookupPhone(event.target.value)
                                    }
                                    placeholder="+55 (11) 99999-8888"
                                    autoComplete="tel"
                                />
                            </div>
                        )}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={resetConversation}
                        disabled={isStreaming}
                    >
                        Reiniciar
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto pr-1">
                    {displayedMessages.map((item) => (
                        <div
                            className={`flex ${item.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            key={item.id}
                        >
                            <div className="grid max-w-[90%] gap-2">
                                <div
                                    className={`rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap ${item.role === 'user' ? 'bg-primary text-primary-foreground rounded-br-sm' : item.isError ? 'border-destructive/40 bg-destructive/10 text-destructive rounded-bl-sm border' : 'bg-muted rounded-bl-sm'}`}
                                >
                                    {item.content ||
                                        (item.isStreaming && <Spinner />)}
                                    {item.isStreaming &&
                                        item.content !== '' && (
                                            <span className="bg-foreground/60 ml-1 inline-block size-1.5 animate-pulse rounded-full align-middle" />
                                        )}
                                </div>
                                {item.role === 'assistant' &&
                                    item.id !== 'welcome' && (
                                        <Collapsible>
                                            <CollapsibleTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="text-muted-foreground hover:text-foreground flex w-fit items-center gap-1 text-xs transition-colors"
                                                >
                                                    <BookOpen className="size-3.5" />
                                                    {messageSourcesLabel(item)}
                                                    <ChevronDown className="size-3.5" />
                                                </button>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent className="mt-2 grid gap-3 rounded-lg border p-3">
                                                {item.baseConsulted ? (
                                                    item.sources.length > 0 ? (
                                                        item.sources.map(
                                                            (source) => (
                                                                <div
                                                                    className="grid gap-2"
                                                                    key={sourceKey(
                                                                        source,
                                                                    )}
                                                                >
                                                                    <div className="flex items-start justify-between gap-3">
                                                                        <div className="min-w-0">
                                                                            <p className="truncate text-sm font-medium">
                                                                                {
                                                                                    source
                                                                                        .document
                                                                                        .title
                                                                                }
                                                                            </p>
                                                                            <p className="text-muted-foreground truncate text-xs">
                                                                                {
                                                                                    source
                                                                                        .collection
                                                                                        .name
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="shrink-0 tabular-nums"
                                                                        >
                                                                            {Math.round(
                                                                                source.similarity *
                                                                                    100,
                                                                            )}
                                                                            %
                                                                        </Badge>
                                                                    </div>
                                                                    <p className="text-muted-foreground bg-muted/50 rounded-lg p-3 text-xs leading-relaxed">
                                                                        {
                                                                            source.content
                                                                        }
                                                                    </p>
                                                                    <Separator />
                                                                </div>
                                                            ),
                                                        )
                                                    ) : (
                                                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                            <CheckCircle2 className="text-primary size-4" />
                                                            A base foi
                                                            consultada, mas não
                                                            há trechos
                                                            relevantes.
                                                        </div>
                                                    )
                                                ) : (
                                                    <p className="text-muted-foreground text-xs">
                                                        Esta resposta não
                                                        consultou a Base de
                                                        conhecimento.
                                                    </p>
                                                )}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    )}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="border-input bg-background focus-within:ring-ring/30 flex items-end gap-2 rounded-xl border p-2 focus-within:ring-2">
                    <textarea
                        value={messageDraft}
                        onChange={(event) =>
                            setMessageDraft(event.target.value)
                        }
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
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
                        type="button"
                        size="icon"
                        aria-label="Enviar mensagem"
                        onClick={() => void sendMessage()}
                        disabled={isStreaming || messageDraft.trim() === ''}
                    >
                        {isStreaming ? <Spinner /> : <Send />}
                    </Button>
                </div>
                <p className="text-muted-foreground text-center text-xs">
                    Limite de 60 mensagens por hora por usuário · Enter envia ·
                    Shift + Enter quebra linha
                </p>
            </CardContent>
        </Card>
    );
}
