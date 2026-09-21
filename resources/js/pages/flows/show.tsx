import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    Eye,
    GitBranch,
    History,
    Plus,
    Redo2,
    Sparkles,
    Undo2,
    Upload,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, pause, publish, resume, update } from '@/routes/flows';
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

type Props = {
    flow: Flow;
    channels: Channel[];
    nodeCatalog: NodeCatalogItem[];
    permissions: TeamPermissions;
};

type PanelMode = 'palette' | 'properties' | null;

export default function FlowShow({
    flow,
    channels,
    nodeCatalog,
    permissions,
}: Props) {
    const canEdit = permissions.canManageFlows;
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
        'design',
    );
    const [aiOpen, setAiOpen] = useState(false);
    const [playgroundOpen, setPlaygroundOpen] = useState(false);
    const [traceNodeIds, setTraceNodeIds] = useState<string[]>([]);
    const [connectionSource, setConnectionSource] = useState<string | null>(
        null,
    );
    const [publishErrors, setPublishErrors] = useState<string[]>([]);
    const selectedNode =
        nodes.find((node) => node.id === selectedNodeId) ?? null;

    const saveDraft = (nextNodes: FlowNode[], nextEdges: FlowEdge[]) => {
        router.patch(
            update(flow.id).url,
            {
                dsl: JSON.parse(
                    JSON.stringify({
                        version: flow.draft.version ?? 1,
                        nodes: nextNodes,
                        edges: nextEdges,
                    }),
                ),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const applyGraph = (nextNodes: FlowNode[], nextEdges: FlowEdge[]) => {
        setHistory((current) => [...current, { nodes, edges }]);
        setFuture([]);
        setNodes(nextNodes);
        setEdges(nextEdges);
        saveDraft(nextNodes, nextEdges);
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
            x: 80 + nodes.length * 300,
            y: 190,
        };
        const newNode: FlowNode = {
            id,
            type,
            config: defaultConfig(type),
            position: { x: afterPosition.x + 290, y: afterPosition.y },
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
        const nextNodes = nodes.map((node) =>
            node.id === nodeId ? { ...node, config } : node,
        );
        setNodes(nextNodes);
        saveDraft(nextNodes, edges);
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

    const updateMeta = (changes: Record<string, string | null>) => {
        if (!canEdit) {
            return;
        }
        router.patch(update(flow.id).url, changes, {
            preserveState: true,
            preserveScroll: true,
        });
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
        saveDraft(previous.nodes, previous.edges);
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
        saveDraft(next.nodes, next.edges);
    };

    const publishFlow = () => {
        setPublishErrors([]);
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

    return (
        <>
            <Head title={`Fluxo · ${flow.name}`} />
            <div className="relative flex min-h-[calc(100vh-4rem)] flex-1 flex-col overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-start justify-between p-5">
                    <div className="bg-background/95 pointer-events-auto flex max-w-[min(420px,calc(100%-1rem))] items-center gap-3 rounded-2xl border p-2 shadow-lg backdrop-blur">
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
                                defaultValue={flow.name}
                                disabled={!canEdit}
                                onBlur={(event) => {
                                    if (
                                        event.target.value.trim() !== '' &&
                                        event.target.value !== flow.name
                                    ) {
                                        updateMeta({
                                            name: event.target.value,
                                        });
                                    }
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
                                {flow.hasUnpublishedChanges && (
                                    <span className="text-signal-attention text-[11px]">
                                        Alterações não publicadas
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="bg-background/95 pointer-events-auto flex items-center gap-1 rounded-2xl border p-1.5 shadow-lg backdrop-blur">
                        <div className="mr-1 flex items-center gap-1 border-r pr-2">
                            <Button
                                variant={
                                    activeTab === 'design'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() => setActiveTab('design')}
                            >
                                <GitBranch /> Desenho
                            </Button>
                            <Button
                                variant={
                                    activeTab === 'executions'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() => setActiveTab('executions')}
                            >
                                <History /> Execuções
                            </Button>
                        </div>
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
                                onClick={() => setAiOpen(true)}
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
                                          : publishFlow
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

                {publishErrors.length > 0 && (
                    <div className="bg-destructive/10 text-destructive border-destructive/30 absolute top-24 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2 rounded-xl border px-4 py-3 text-sm shadow-lg">
                        <span>{publishErrors[0]}</span>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => setPublishErrors([])}
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
                        onSelectNode={(nodeId) => {
                            setSelectedNodeId(nodeId);
                            setPanelMode('properties');
                        }}
                        onMoveNode={moveNode}
                        onDeleteNode={deleteNode}
                        onAddAfter={(nodeId) => {
                            setPaletteAfter(nodeId);
                            setPanelMode('palette');
                        }}
                        onStartConnection={(nodeId) =>
                            setConnectionSource(nodeId)
                        }
                        onFinishConnection={(nodeId) => {
                            if (connectionSource !== null) {
                                connectNodes(connectionSource, nodeId);
                                setConnectionSource(null);
                            }
                        }}
                        onDeleteEdge={(edge) =>
                            applyGraph(
                                nodes,
                                edges.filter((candidate) => candidate !== edge),
                            )
                        }
                        onChooseTrigger={() => {
                            setPaletteAfter(null);
                            setPanelMode('palette');
                        }}
                    />
                ) : (
                    <div className="bg-muted/20 flex flex-1 items-center justify-center p-8">
                        <div className="max-w-sm text-center">
                            <History className="text-muted-foreground mx-auto size-8" />
                            <h2 className="mt-4 font-semibold">
                                Execuções aparecem aqui
                            </h2>
                            <p className="text-muted-foreground mt-2 text-sm leading-6">
                                Publique e acompanhe os turnos reais nesta área.
                                O Playground fica fora das métricas.
                            </p>
                        </div>
                    </div>
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
                        node={selectedNode}
                        catalog={nodeCatalog}
                        channels={channels}
                        triggerType={flow.triggerType}
                        triggerEvent={flow.triggerEvent}
                        channelId={flow.channelId}
                        onChangeConfig={changeNodeConfig}
                        onChangeFlowMeta={updateMeta}
                        onClose={() => setPanelMode(null)}
                    />
                )}

                {aiOpen && (
                    <FlowAiSheet
                        flowId={flow.id}
                        open={aiOpen}
                        onClose={() => setAiOpen(false)}
                        onApply={(dsl) => {
                            setNodes(dsl.nodes);
                            setEdges(dsl.edges);
                            setTraceNodeIds([]);
                            setAiOpen(false);
                        }}
                    />
                )}
                <FlowPlaygroundPanel
                    flowId={flow.id}
                    triggerType={flow.triggerType}
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
        <aside className="bg-background absolute top-1/2 left-1/2 z-30 flex max-h-[min(650px,calc(100%-8rem))] w-[min(460px,calc(100%-2rem))] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border shadow-2xl">
            <div className="flex items-start justify-between border-b p-5">
                <div>
                    <p className="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">
                        Biblioteca
                    </p>
                    <h2 className="mt-1 font-semibold">Adicionar um passo</h2>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar seletor de Nós"
                    onClick={onClose}
                >
                    ×
                </Button>
            </div>
            <div className="border-b p-4">
                <Input
                    autoFocus
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Buscar pelo nome..."
                />
            </div>
            <div className="flex-1 overflow-y-auto p-3">
                {categories.map((category) => (
                    <div key={category} className="mb-4 last:mb-0">
                        <p className="text-muted-foreground px-2 pb-2 text-[11px] font-medium tracking-[0.14em] uppercase">
                            {category}
                        </p>
                        <div className="grid gap-1">
                            {filtered
                                .filter((item) => item.category === category)
                                .map((item) => (
                                    <button
                                        key={item.type}
                                        type="button"
                                        className="hover:bg-muted/60 flex items-start gap-3 rounded-xl px-2 py-3 text-left transition-colors"
                                        onClick={() => onSelect(item.type)}
                                    >
                                        <span className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
                                            <Plus className="size-4" />
                                        </span>
                                        <span className="min-w-0">
                                            <strong className="block text-sm font-medium">
                                                {item.label}
                                            </strong>
                                            <span className="text-muted-foreground mt-0.5 block text-xs leading-5">
                                                {item.description}
                                            </span>
                                        </span>
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

function defaultConfig(type: string): Record<string, unknown> {
    return {
        ...(type === 'message' ? { text: '' } : {}),
        ...(type === 'capture_response'
            ? { question: '', variable: 'response', format: 'text' }
            : {}),
        ...(type === 'wait' ? { seconds: 60 } : {}),
        ...(type === 'condition' ? { expression: '' } : {}),
    };
}

function newId(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID().slice(0, 8)
        : Math.random().toString(36).slice(2, 10);
}
