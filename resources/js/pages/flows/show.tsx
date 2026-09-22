import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    Eye,
    GitBranch,
    History,
    List,
    LoaderCircle,
    Redo2,
    Save,
    Search,
    Send,
    Sparkles,
    Undo2,
    Upload,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { FlowAiSheet } from '@/components/flows/flow-ai-sheet';
import {
    FlowCanvas,
    type FlowEdge,
    type FlowNode,
    type NodeCatalogItem,
} from '@/components/flows/flow-canvas';
import { FlowNodeProperties } from '@/components/flows/flow-node-properties';
import { FlowPlaygroundPanel } from '@/components/flows/flow-playground-panel';
import {
    FlowExecutionsDashboard,
    type FlowObservability,
    type PaginatedExecutions,
} from '@/components/flows/flow-executions-dashboard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { patchJson } from '@/lib/flow-http';
import { index, pause, publish, resume, show, update } from '@/routes/flows';
import { cn } from '@/lib/utils';
import type { TeamPermissions } from '@/types';

type DraftDsl = {
    version: number;
    nodes: FlowNode[];
    edges: FlowEdge[];
};

type Flow = {
    id: string;
    name: string;
    status: 'draft' | 'published' | 'paused';
    statusLabel: string;
    draft: DraftDsl;
    hasUnpublishedChanges: boolean;
    channelId: string | null;
    triggerType: string;
    triggerEvent: string | null;
    category: string | null;
    categoryLabel: string | null;
};

type Channel = {
    id: string;
    label: string;
};

type Integration = {
    id: string;
    name: string;
    type: string;
    status: string;
    statusLabel: string;
};

type FlowEvent = {
    id: string;
    name: string;
    description: string | null;
};

type WhatsAppTemplate = {
    id: number;
    name: string;
    language: string;
    channelId: string;
    category: string;
    body: string;
    variables: { position: number; component: string }[];
};

type Props = {
    flow: Flow;
    channels: Channel[];
    integrations: Integration[];
    events: FlowEvent[];
    whatsappTemplates: WhatsAppTemplate[];
    nodeCatalog: NodeCatalogItem[];
    permissions: TeamPermissions;
    activeTab: 'design' | 'executions';
    observability: FlowObservability;
    executionFilters: {
        status: 'failed' | 'stuck' | 'all';
        error: string | null;
    };
    executions: PaginatedExecutions;
};

type PanelMode = 'palette' | 'properties' | null;

type FlowMeta = {
    name: string;
    triggerType: string;
    triggerEvent: string | null;
    channelId: string | null;
};

export default function FlowShow({
    flow,
    channels,
    integrations,
    events,
    whatsappTemplates,
    nodeCatalog,
    permissions,
    activeTab: initialActiveTab,
    observability,
    executionFilters,
    executions,
}: Props) {
    const canEdit = permissions.canManageFlows;
    const [flowMeta, setFlowMeta] = useState<FlowMeta>(() => ({
        name: flow.name,
        triggerType: flow.triggerType,
        triggerEvent: flow.triggerEvent,
        channelId: flow.channelId,
    }));
    const [nodes, setNodes] = useState<FlowNode[]>(flow.draft.nodes ?? []);
    const [edges, setEdges] = useState<FlowEdge[]>(flow.draft.edges ?? []);
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const [panelMode, setPanelMode] = useState<PanelMode>(null);
    const [paletteAfter, setPaletteAfter] = useState<string | null>(null);
    const [history, setHistory] = useState<
        Array<{ nodes: FlowNode[]; edges: FlowEdge[] }>
    >([]);
    const [future, setFuture] = useState<
        Array<{ nodes: FlowNode[]; edges: FlowEdge[] }>
    >([]);
    const [activeTab, setActiveTab] = useState<'design' | 'executions'>(
        initialActiveTab,
    );
    const [aiOpen, setAiOpen] = useState(false);
    const [aiInitialMessage, setAiInitialMessage] = useState('');
    const [emptyTab, setEmptyTab] = useState<'add' | 'ai'>('ai');
    const [playgroundOpen, setPlaygroundOpen] = useState(false);
    const [traceNodeIds, setTraceNodeIds] = useState<string[]>([]);
    const [publishErrors, setPublishErrors] = useState<string[]>([]);
    const [hasUnpublishedChanges, setHasUnpublishedChanges] = useState(
        flow.hasUnpublishedChanges,
    );
    const [saveError, setSaveError] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [revision, setRevision] = useState(0);
    const [savedRevision, setSavedRevision] = useState(0);
    const selectedNode =
        nodes.find((node) => node.id === selectedNodeId) ?? null;
    const hasUnsavedChanges = revision !== savedRevision;

    const markChanged = () => {
        setRevision((current) => current + 1);
        setSaveError(null);
    };

    const saveFlow = async (): Promise<boolean> => {
        if (!canEdit || !hasUnsavedChanges || isSaving) {
            return !isSaving;
        }
        if (flowMeta.name.trim() === '') {
            setSaveError('Dê um nome ao Fluxo antes de salvar.');

            return false;
        }

        const revisionToSave = revision;
        setIsSaving(true);
        setSaveError(null);

        try {
            await patchJson(update(flow.id).url, {
                name: flowMeta.name.trim(),
                trigger_type: flowMeta.triggerType,
                trigger_event: flowMeta.triggerEvent,
                channel_id: flowMeta.channelId,
                dsl: JSON.parse(
                    JSON.stringify({
                        version: flow.draft.version ?? 1,
                        nodes,
                        edges,
                    }),
                ),
            });
            setFlowMeta((current) => ({
                ...current,
                name: current.name.trim(),
            }));
            setSavedRevision((current) => Math.max(current, revisionToSave));
            setHasUnpublishedChanges(true);

            return true;
        } catch (error) {
            setSaveError(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível salvar o Fluxo.',
            );

            return false;
        } finally {
            setIsSaving(false);
        }
    };

    const applyGraph = (nextNodes: FlowNode[], nextEdges: FlowEdge[]) => {
        setHistory((current) => [...current, { nodes, edges }]);
        setFuture([]);
        setNodes(nextNodes);
        setEdges(nextEdges);
        markChanged();
    };

    const addNode = (type: string, afterId: string | null = paletteAfter) => {
        if (!canEdit) {
            return;
        }
        const id = `${type}-${newId()}`;
        const afterNode =
            afterId === null
                ? nodes[nodes.length - 1]
                : nodes.find((node) => node.id === afterId);
        const afterPosition = afterNode?.position ?? {
            x: 80 + nodes.length * 260,
            y: 190,
        };
        const newNode: FlowNode = {
            id,
            type,
            config: defaultConfig(type),
            position: { x: afterPosition.x + 250, y: afterPosition.y },
        };
        let nextEdges = [...edges];
        if (afterNode !== undefined) {
            const outgoing = nextEdges.find(
                (edge) => edge.source === afterNode.id,
            );
            if (outgoing !== undefined) {
                nextEdges = nextEdges.map((edge) =>
                    edge === outgoing ? { ...edge, target: id } : edge,
                );
                nextEdges.push({
                    id: `${id}-next`,
                    source: id,
                    target: outgoing.target,
                });
            } else {
                nextEdges.push({
                    id: `${afterNode.id}-${id}`,
                    source: afterNode.id,
                    target: id,
                });
            }
        }
        const nextNodes = [...nodes, newNode];
        applyGraph(nextNodes, nextEdges);
        setSelectedNodeId(id);
        setPanelMode('properties');
        setPaletteAfter(null);
    };

    const deleteNode = (nodeId: string) => {
        if (!canEdit) {
            return;
        }
        applyGraph(
            nodes.filter((node) => node.id !== nodeId),
            edges.filter(
                (edge) => edge.source !== nodeId && edge.target !== nodeId,
            ),
        );
        setSelectedNodeId(null);
        setPanelMode(null);
    };

    const moveNode = (nodeId: string, position: { x: number; y: number }) => {
        if (!canEdit) {
            return;
        }
        applyGraph(
            nodes.map((node) =>
                node.id === nodeId ? { ...node, position } : node,
            ),
            edges,
        );
    };

    const changeNodeConfig = (
        nodeId: string,
        config: Record<string, unknown>,
    ) => {
        if (!canEdit) {
            return;
        }
        setNodes((current) =>
            current.map((node) =>
                node.id === nodeId ? { ...node, config } : node,
            ),
        );
        markChanged();
    };

    const connectNodes = (source: string, target: string) => {
        if (
            !canEdit ||
            source === target ||
            edges.some(
                (edge) => edge.source === source && edge.target === target,
            )
        ) {
            return;
        }
        applyGraph(nodes, [
            ...edges,
            { id: `${source}-${target}-${newId()}`, source, target },
        ]);
    };

    const openPropertiesFor = (nodeId: string) => {
        setAiOpen(false);
        setSelectedNodeId(nodeId);
        setPanelMode('properties');
    };

    const openPaletteAfter = (nodeId: string | null) => {
        setAiOpen(false);
        setPaletteAfter(nodeId);
        setPanelMode('palette');
    };

    const openAiChat = (initialMessage = '') => {
        setPanelMode(null);
        setAiInitialMessage(initialMessage);
        setAiOpen(true);
    };

    const updateMeta = (changes: Record<string, string | null>) => {
        if (!canEdit) {
            return;
        }
        setFlowMeta((current) => ({
            name: changes.name ?? current.name,
            triggerType: changes.trigger_type ?? current.triggerType,
            triggerEvent:
                changes.trigger_event !== undefined
                    ? changes.trigger_event
                    : current.triggerEvent,
            channelId:
                changes.channel_id !== undefined
                    ? changes.channel_id
                    : current.channelId,
        }));
        markChanged();
    };

    const undo = () => {
        const previous = history.at(-1);
        if (previous === undefined) {
            return;
        }
        setHistory((current) => current.slice(0, -1));
        setFuture((current) => [...current, { nodes, edges }]);
        setNodes(previous.nodes);
        setEdges(previous.edges);
        markChanged();
    };

    const redo = () => {
        const next = future.at(-1);
        if (next === undefined) {
            return;
        }
        setFuture((current) => current.slice(0, -1));
        setHistory((current) => [...current, { nodes, edges }]);
        setNodes(next.nodes);
        setEdges(next.edges);
        markChanged();
    };

    const publishFlow = async () => {
        setPublishErrors([]);
        const saved = await saveFlow();
        if (!saved) {
            return;
        }
        router.post(
            publish(flow.id).url,
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    const message = errors.dsl;
                    const errorMessage =
                        message ?? 'Revise o desenho antes de publicar.';
                    const culprit = nodes.find((node) =>
                        errorMessage.includes(node.id),
                    );
                    if (culprit !== undefined) {
                        setSelectedNodeId(culprit.id);
                        setPanelMode('properties');
                    }
                    setPublishErrors([errorMessage]);
                },
            },
        );
    };

    const toggleStatus = () => {
        const action = flow.status === 'published' ? pause : resume;
        router.post(action(flow.id).url, {}, { preserveScroll: true });
    };

    const changePeriod = (period: '24h' | '7d' | '30d') => {
        router.get(
            show.url(flow.id, {
                query: {
                    tab: 'executions',
                    period,
                    execution_status: executionFilters.status,
                    error: executionFilters.error,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const firstExecutionTraceUrl = executions.data[0]?.traceUrl ?? null;

    return (
        <>
            <Head title={`Fluxo · ${flowMeta.name}`} />
            <div
                className={`relative flex min-h-[calc(100vh-4rem)] flex-1 flex-col ${activeTab === 'design' ? 'overflow-hidden' : 'overflow-y-auto'}`}
            >
                {activeTab === 'executions' && (
                    <header className="border-border/70 bg-background flex h-14 shrink-0 items-center gap-3 border-b px-5">
                        <Button
                            variant="outline"
                            size="icon"
                            asChild
                            aria-label="Voltar para Fluxos"
                            className="size-[30px]"
                        >
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <span className="text-sm font-semibold">
                            {flowMeta.name}
                        </span>
                        <span className="flex-1" />
                        <div className="bg-muted flex items-center gap-0.5 rounded-lg p-1">
                            <button
                                type="button"
                                onClick={() => setActiveTab('design')}
                                className="text-muted-foreground rounded-md px-3 py-1 text-[12.5px] font-medium"
                            >
                                Desenho
                            </button>
                            <span className="bg-background rounded-md px-3 py-1 text-[12.5px] font-semibold shadow-sm">
                                Execuções
                            </span>
                        </div>
                        <span className="flex-1" />
                        <div className="bg-muted flex items-center gap-0.5 rounded-lg p-1">
                            {(
                                [
                                    { value: '24h', label: '24 h' },
                                    { value: '7d', label: '7 dias' },
                                    { value: '30d', label: '30 dias' },
                                ] as const
                            ).map((period) => (
                                <button
                                    key={period.value}
                                    type="button"
                                    onClick={() => changePeriod(period.value)}
                                    className={`rounded-md px-2.5 py-1 text-[12px] font-medium ${
                                        observability.period === period.value
                                            ? 'bg-background shadow-sm'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {period.label}
                                </button>
                            ))}
                        </div>
                        {firstExecutionTraceUrl !== null && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={firstExecutionTraceUrl}>
                                    <List /> Ver lista de execuções
                                </Link>
                            </Button>
                        )}
                    </header>
                )}

                {activeTab === 'design' && (
                    <div className="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-start justify-between p-5">
                        <div className="bg-background/95 pointer-events-auto flex max-w-[min(420px,calc(100%-1rem))] items-center gap-2 rounded-2xl border p-2 shadow-lg backdrop-blur">
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                aria-label="Voltar para Fluxos"
                            >
                                <Link href={index()}>
                                    <ArrowLeft />
                                </Link>
                            </Button>
                            <div className="min-w-0 px-1">
                                <Input
                                    className="h-7 border-0 bg-transparent px-0 text-sm font-semibold shadow-none focus-visible:ring-0"
                                    value={flowMeta.name}
                                    disabled={!canEdit}
                                    onChange={(event) => {
                                        setFlowMeta((current) => ({
                                            ...current,
                                            name: event.target.value,
                                        }));
                                        markChanged();
                                    }}
                                />
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className="h-5 px-1.5 text-[10px]"
                                    >
                                        {flow.categoryLabel ?? 'Rascunho'}
                                    </Badge>
                                    <span className="text-muted-foreground text-[11px]">
                                        {flow.statusLabel}
                                    </span>
                                    {hasUnsavedChanges ? (
                                        <span className="text-signal-attention text-[11px]">
                                            Alterações não salvas
                                        </span>
                                    ) : hasUnpublishedChanges ? (
                                        <span className="text-signal-attention text-[11px]">
                                            Alterações não publicadas
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                        <div className="bg-background/95 pointer-events-auto flex items-center gap-1 rounded-2xl border p-1.5 shadow-lg backdrop-blur">
                            <div className="mr-1 flex items-center gap-1 border-r pr-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setActiveTab('design')}
                                >
                                    <GitBranch /> Desenho
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setActiveTab('executions')}
                                >
                                    <History /> Execuções
                                </Button>
                            </div>
                            {canEdit && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!hasUnsavedChanges || isSaving}
                                    onClick={() => void saveFlow()}
                                >
                                    {isSaving ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Save />
                                    )}
                                    {isSaving ? 'Salvando' : 'Salvar'}
                                </Button>
                            )}
                            {canEdit && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Desfazer"
                                    disabled={history.length === 0}
                                    onClick={undo}
                                >
                                    <Undo2 />
                                </Button>
                            )}
                            {canEdit && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Refazer"
                                    disabled={future.length === 0}
                                    onClick={redo}
                                >
                                    <Redo2 />
                                </Button>
                            )}
                            {canEdit && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openAiChat()}
                                >
                                    <Sparkles /> Criar com IA
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPlaygroundOpen(true)}
                            >
                                <Eye /> Testar
                            </Button>
                            {canEdit && (
                                <Button
                                    size="sm"
                                    onClick={
                                        flow.status === 'published'
                                            ? toggleStatus
                                            : flow.status === 'paused'
                                              ? toggleStatus
                                              : () => void publishFlow()
                                    }
                                >
                                    {flow.status === 'published' ? (
                                        <>
                                            <ChevronDown /> Pausar
                                        </>
                                    ) : flow.status === 'paused' ? (
                                        <>
                                            <Upload /> Retomar
                                        </>
                                    ) : (
                                        <>
                                            <Upload /> Publicar
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                {activeTab === 'design' &&
                    (saveError !== null || publishErrors.length > 0) && (
                        <div className="bg-destructive/10 text-destructive border-destructive/30 absolute top-24 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2 rounded-xl border px-4 py-3 text-sm shadow-lg">
                            <span>{saveError ?? publishErrors[0]}</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => {
                                    setSaveError(null);
                                    setPublishErrors([]);
                                }}
                            >
                                ×
                            </Button>
                        </div>
                    )}

                {activeTab === 'design' ? (
                    <FlowCanvas
                        nodes={nodes}
                        edges={edges}
                        catalog={nodeCatalog}
                        selectedNodeId={selectedNodeId}
                        traceNodeIds={traceNodeIds}
                        editable={canEdit}
                        onSelectNode={openPropertiesFor}
                        onMoveNode={moveNode}
                        onDeleteNode={deleteNode}
                        onAddAfter={openPaletteAfter}
                        onConnect={connectNodes}
                        onDeleteEdge={(edge) =>
                            applyGraph(
                                nodes,
                                edges.filter((candidate) => candidate !== edge),
                            )
                        }
                        onChooseTrigger={() => setEmptyTab('add')}
                    />
                ) : (
                    <FlowExecutionsDashboard
                        analytics={observability}
                        tracesUrl={firstExecutionTraceUrl}
                    />
                )}

                {activeTab === 'design' && canEdit && nodes.length === 0 && (
                    <EmptyEditorAside
                        tab={emptyTab}
                        onTabChange={setEmptyTab}
                        catalog={nodeCatalog}
                        onSelectNode={(type) => addNode(type)}
                        onStartAi={(text) => openAiChat(text)}
                    />
                )}

                {canEdit && panelMode === 'palette' && (
                    <NodePalette
                        catalog={nodeCatalog}
                        onClose={() => setPanelMode(null)}
                        onSelect={(type) => addNode(type)}
                    />
                )}
                {selectedNode && panelMode === 'properties' && (
                    <FlowNodeProperties
                        key={selectedNode.id}
                        node={selectedNode}
                        catalog={nodeCatalog}
                        channels={channels}
                        integrations={integrations}
                        events={events}
                        whatsappTemplates={whatsappTemplates}
                        triggerType={flowMeta.triggerType}
                        triggerEvent={flowMeta.triggerEvent}
                        channelId={flowMeta.channelId}
                        onChangeConfig={changeNodeConfig}
                        onChangeFlowMeta={updateMeta}
                        onClose={() => setPanelMode(null)}
                    />
                )}

                {aiOpen && (
                    <FlowAiSheet
                        flowId={flow.id}
                        open={aiOpen}
                        initialMessage={aiInitialMessage}
                        onClose={() => setAiOpen(false)}
                        onApply={(dsl) => {
                            setHistory((current) => [
                                ...current,
                                { nodes, edges },
                            ]);
                            setFuture([]);
                            setNodes(dsl.nodes);
                            setEdges(dsl.edges);
                            setTraceNodeIds([]);
                            setAiOpen(false);
                            markChanged();
                        }}
                    />
                )}
                <FlowPlaygroundPanel
                    flowId={flow.id}
                    triggerType={flowMeta.triggerType}
                    open={playgroundOpen}
                    onClose={() => {
                        setPlaygroundOpen(false);
                        setTraceNodeIds([]);
                    }}
                    onTrace={setTraceNodeIds}
                />
            </div>
        </>
    );
}

function NodePalette({
    catalog,
    onClose,
    onSelect,
}: {
    catalog: NodeCatalogItem[];
    onClose: () => void;
    onSelect: (type: string) => void;
}) {
    const [query, setQuery] = useState('');
    const filtered = catalog.filter((item) =>
        `${item.label} ${item.description} ${item.category}`
            .toLowerCase()
            .includes(query.toLowerCase()),
    );
    const categories = [...new Set(filtered.map((item) => item.category))];

    return (
        <aside className="bg-background absolute top-[76px] right-4 z-30 flex max-h-[min(480px,calc(100%-9rem))] w-[322px] flex-col overflow-hidden rounded-xl border shadow-lg">
            <div className="flex items-center gap-2 border-b p-2.5">
                <div className="bg-muted/60 border-input flex h-8 flex-1 items-center gap-2 rounded-lg border px-2.5">
                    <Search className="text-muted-foreground size-3.5 shrink-0" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Buscar nó…"
                        className="placeholder:text-muted-foreground w-full bg-transparent text-[12.5px] outline-none"
                    />
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar seletor de Nós"
                    className="size-7 shrink-0"
                    onClick={onClose}
                >
                    <X className="size-3.5" />
                </Button>
            </div>
            <div className="flex-1 overflow-y-auto p-3">
                {categories.map((category) => (
                    <div key={category} className="mb-3.5 last:mb-0">
                        <p className="text-muted-foreground mb-1.5 px-1 text-[10px] font-bold tracking-[0.07em] uppercase">
                            {category}
                        </p>
                        <div className="grid gap-0.5">
                            {filtered
                                .filter((item) => item.category === category)
                                .map((item) => (
                                    <button
                                        key={item.type}
                                        type="button"
                                        className="hover:bg-muted flex items-center gap-2.5 rounded-lg px-2 py-[7px] text-left text-[12.5px] font-medium transition-colors"
                                        onClick={() => onSelect(item.type)}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                        </div>
                    </div>
                ))}
                {filtered.length === 0 && (
                    <p className="text-muted-foreground p-6 text-center text-sm">
                        Nenhum Nó encontrado.
                    </p>
                )}
            </div>
        </aside>
    );
}

const emptyEditorStarters = [
    'Qualificar quem chega e passar os quentes para a equipe comercial',
    'Marcar horário consultando a agenda e confirmar por mensagem',
    'Responder dúvidas pela Base de conhecimento e escalar o que não souber',
];

function EmptyEditorAside({
    tab,
    onTabChange,
    catalog,
    onSelectNode,
    onStartAi,
}: {
    tab: 'add' | 'ai';
    onTabChange: (tab: 'add' | 'ai') => void;
    catalog: NodeCatalogItem[];
    onSelectNode: (type: string) => void;
    onStartAi: (message: string) => void;
}) {
    const [query, setQuery] = useState('');
    const [prompt, setPrompt] = useState('');
    const filtered = catalog.filter((item) =>
        `${item.label} ${item.description} ${item.category}`
            .toLowerCase()
            .includes(query.toLowerCase()),
    );
    const categories = [...new Set(filtered.map((item) => item.category))];

    return (
        <aside className="bg-background absolute top-[84px] right-0 bottom-0 z-20 flex w-[360px] flex-col border-l shadow-lg">
            <div className="bg-muted/40 flex shrink-0 gap-0.5 border-b p-2.5">
                <button
                    type="button"
                    onClick={() => onTabChange('add')}
                    className={cn(
                        'flex-1 rounded-md py-1.5 text-[12px] font-semibold transition-colors',
                        tab === 'add'
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground',
                    )}
                >
                    Adicionar
                </button>
                <span className="text-muted-foreground/50 flex-1 py-1.5 text-center text-[12px] font-semibold">
                    Propriedades
                </span>
                <button
                    type="button"
                    onClick={() => onTabChange('ai')}
                    className={cn(
                        'flex-1 rounded-md py-1.5 text-[12px] font-semibold transition-colors',
                        tab === 'ai'
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground',
                    )}
                >
                    Criar com IA
                </button>
            </div>

            {tab === 'add' ? (
                <>
                    <div className="border-b p-3">
                        <div className="bg-muted/50 border-input flex h-8 items-center gap-2 rounded-lg border px-2.5">
                            <Search className="text-muted-foreground size-3.5 shrink-0" />
                            <input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Buscar nó…"
                                className="placeholder:text-muted-foreground w-full bg-transparent text-[12.5px] outline-none"
                            />
                        </div>
                    </div>
                    <div className="flex-1 overflow-y-auto p-3">
                        {categories.map((category) => (
                            <div key={category} className="mb-3.5 last:mb-0">
                                <p className="text-muted-foreground mb-1.5 px-1 text-[10px] font-bold tracking-[0.07em] uppercase">
                                    {category}
                                </p>
                                <div className="grid gap-0.5">
                                    {filtered
                                        .filter(
                                            (item) =>
                                                item.category === category,
                                        )
                                        .map((item) => (
                                            <button
                                                key={item.type}
                                                type="button"
                                                onClick={() =>
                                                    onSelectNode(item.type)
                                                }
                                                className="hover:bg-muted flex items-center gap-2.5 rounded-lg px-2 py-[7px] text-left text-[12.5px] font-medium transition-colors"
                                            >
                                                {item.label}
                                            </button>
                                        ))}
                                </div>
                            </div>
                        ))}
                        {filtered.length === 0 && (
                            <p className="text-muted-foreground p-6 text-center text-sm">
                                Nenhum Nó encontrado.
                            </p>
                        )}
                    </div>
                </>
            ) : (
                <div className="flex flex-1 flex-col overflow-y-auto p-4">
                    <span className="bg-muted flex size-10 items-center justify-center rounded-[11px]">
                        <Sparkles className="size-5" />
                    </span>
                    <h2 className="mt-3.5 text-[15px] font-semibold">
                        Descreva o atendimento
                    </h2>
                    <p className="text-muted-foreground mt-1.5 text-[12.5px] leading-[1.55]">
                        Conte como o atendimento deveria funcionar, em português
                        mesmo. Eu monto os nós e você ajusta no canvas.
                    </p>

                    <div className="mt-[18px] flex flex-col gap-[7px]">
                        <p className="text-muted-foreground mb-0.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                            Comece por aqui
                        </p>
                        {emptyEditorStarters.map((text) => (
                            <button
                                key={text}
                                type="button"
                                onClick={() => onStartAi(text)}
                                className="border-border hover:bg-muted/50 rounded-[9px] border p-[11px_13px] text-left text-[12.5px] leading-[1.45] transition-colors"
                            >
                                {text}
                            </button>
                        ))}
                    </div>

                    <div className="bg-muted mt-[18px] flex gap-[9px] rounded-[9px] p-[12px_13px]">
                        <GitBranch className="text-muted-foreground mt-px size-[15px] shrink-0" />
                        <p className="text-muted-foreground text-[11.5px] leading-[1.5]">
                            Prefere partir de algo pronto? Veja os{' '}
                            <Link href={index()} className="font-semibold">
                                Modelos de fluxo
                            </Link>{' '}
                            e clone um.
                        </p>
                    </div>

                    <div className="flex-1" />

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (prompt.trim() !== '') {
                                onStartAi(prompt.trim());
                                setPrompt('');
                            }
                        }}
                        className="border-input bg-muted/40 mt-4 flex h-[38px] shrink-0 items-center gap-2 rounded-[9px] border px-3"
                    >
                        <input
                            value={prompt}
                            onChange={(event) => setPrompt(event.target.value)}
                            placeholder="Descreva o que o fluxo deve fazer…"
                            className="placeholder:text-muted-foreground w-full bg-transparent text-[12.5px] outline-none"
                        />
                        <button
                            type="submit"
                            aria-label="Enviar"
                            className="text-muted-foreground hover:text-foreground shrink-0"
                        >
                            <Send className="size-[15px]" />
                        </button>
                    </form>
                </div>
            )}
        </aside>
    );
}

function defaultConfig(type: string): Record<string, unknown> {
    return {
        ...(type === 'message' ? { text: '' } : {}),
        ...(type === 'capture_response'
            ? { question: '', variable: 'response', format: 'text' }
            : {}),
        ...(type === 'wait' ? { seconds: 60 } : {}),
        ...(type === 'condition' ? { expression: '' } : {}),
        ...(type === 'http_request'
            ? { method: 'POST', url: '', body: {}, response_map: {} }
            : {}),
        ...(type === 'emit_event' ? { event_id: '', payload: {} } : {}),
        ...(type === 'send_template'
            ? { template_id: '', purpose: 'utility', variables: [] }
            : {}),
        ...(type === 'agenda_availability'
            ? { start: '', end: '', output_variable: 'available_slots' }
            : {}),
        ...(type === 'agenda_create_event'
            ? {
                  title: '',
                  start: '',
                  end: '',
                  output_variable: 'appointment_id',
              }
            : {}),
    };
}

function newId(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID().slice(0, 8)
        : Math.random().toString(36).slice(2, 10);
}
