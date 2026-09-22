import {
    Background,
    BaseEdge,
    Controls,
    EdgeLabelRenderer,
    getBezierPath,
    Handle,
    MarkerType,
    Position,
    ReactFlow,
    type Connection,
    type Edge,
    type EdgeProps,
    type Node,
    type NodeChange,
    type NodeProps,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import {
    Bot,
    CalendarCheck,
    CalendarDays,
    CircleStop,
    Clock3,
    GitBranch,
    Globe2,
    LayoutTemplate,
    MessageSquare,
    Plus,
    Radio,
    Route,
    ShieldAlert,
    Sparkles,
    Split,
    Tags,
    TextCursorInput,
    UserRoundCheck,
    Workflow,
    X,
    Zap,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type FlowNode = {
    id: string;
    type: string;
    config: Record<string, unknown>;
    position?: { x: number; y: number };
};

export type FlowEdge = {
    id?: string;
    source: string;
    target: string;
    label?: string;
};

export type NodeCatalogItem = {
    type: string;
    label: string;
    description: string;
    category: string;
    icon: string;
    fields: string[];
};

type FlowNodeData = Record<string, unknown> & {
    domainNode: FlowNode;
    label: string;
    category: string;
    accent: string;
    Icon: LucideIcon;
    editable: boolean;
    traceActive: boolean;
    onDeleteNode: (nodeId: string) => void;
    onAddAfter: (nodeId: string) => void;
};

type FlowReactNode = Node<FlowNodeData, 'flowNode'>;
type FlowEdgeData = Record<string, unknown> & {
    label?: string;
    onDeleteEdge: (edge: FlowEdge) => void;
    domainEdge: FlowEdge;
};
type FlowReactEdge = Edge<FlowEdgeData, 'flowEdge'>;

const icons: Record<string, LucideIcon> = {
    Bot,
    CalendarCheck,
    CalendarDays,
    CircleStop,
    Clock3,
    GitBranch,
    Globe2,
    LayoutTemplate,
    MessageSquare,
    Radio,
    Route,
    ShieldAlert,
    Sparkles,
    Split,
    Tags,
    TextCursorInput,
    UserRoundCheck,
    Workflow,
    Zap,
};

const fallbackIcon = MessageSquare;

const nodeTypes = { flowNode: FlowNodeCard };
const edgeTypes = { flowEdge: FlowEdgeLine };

function iconFor(type: string, catalog: NodeCatalogItem[]): LucideIcon {
    const name = catalog.find((item) => item.type === type)?.icon;

    return icons[name ?? ''] ?? fallbackIcon;
}

function labelFor(type: string, catalog: NodeCatalogItem[]): string {
    return catalog.find((item) => item.type === type)?.label ?? type;
}

function categoryFor(type: string, catalog: NodeCatalogItem[]): string {
    return catalog.find((item) => item.type === type)?.category ?? 'Passo';
}

const categoryAccents: Record<string, string> = {
    Entrada: 'oklch(0.769 0.188 70.08)',
    Inteligência: 'oklch(0.488 0.243 264.376)',
    Conversa: 'oklch(0.6 0.118 184.704)',
    Comunicação: 'oklch(0.6 0.118 184.704)',
    Integrações: 'oklch(0.63 0.14 53)',
};
const defaultAccent = 'var(--muted-foreground)';

function accentFor(type: string, catalog: NodeCatalogItem[]): string {
    return categoryAccents[categoryFor(type, catalog)] ?? defaultAccent;
}

function positionFor(node: FlowNode, index: number): { x: number; y: number } {
    return node.position ?? { x: 96 + index * 260, y: 190 };
}

type FlowCanvasProps = {
    nodes: FlowNode[];
    edges: FlowEdge[];
    catalog: NodeCatalogItem[];
    selectedNodeId: string | null;
    traceNodeIds: string[];
    onSelectNode: (nodeId: string) => void;
    onMoveNode: (nodeId: string, position: { x: number; y: number }) => void;
    onDeleteNode: (nodeId: string) => void;
    onAddAfter: (nodeId: string) => void;
    onConnect: (source: string, target: string) => void;
    onDeleteEdge: (edge: FlowEdge) => void;
    onChooseTrigger: () => void;
    editable?: boolean;
};

export function FlowCanvas({
    nodes,
    edges,
    catalog,
    selectedNodeId,
    traceNodeIds,
    onSelectNode,
    onMoveNode,
    onDeleteNode,
    onAddAfter,
    onConnect,
    onDeleteEdge,
    onChooseTrigger,
    editable = true,
}: FlowCanvasProps) {
    const reactNodes = useMemo<FlowReactNode[]>(
        () =>
            nodes.map((node, index) => ({
                id: node.id,
                type: 'flowNode',
                position: positionFor(node, index),
                selected: selectedNodeId === node.id,
                data: {
                    domainNode: node,
                    label: labelFor(node.type, catalog),
                    category: categoryFor(node.type, catalog),
                    accent: accentFor(node.type, catalog),
                    Icon: iconFor(node.type, catalog),
                    editable,
                    traceActive: traceNodeIds.includes(node.id),
                    onDeleteNode,
                    onAddAfter,
                },
            })),
        [
            catalog,
            editable,
            nodes,
            onAddAfter,
            onDeleteNode,
            selectedNodeId,
            traceNodeIds,
        ],
    );

    const reactEdges = useMemo<FlowReactEdge[]>(
        () =>
            edges.map((edge, index) => ({
                id: edge.id ?? `${edge.source}-${edge.target}-${index}`,
                type: 'flowEdge',
                source: edge.source,
                target: edge.target,
                markerEnd: { type: MarkerType.ArrowClosed },
                data: {
                    label: edge.label,
                    domainEdge: edge,
                    onDeleteEdge,
                },
            })),
        [edges, onDeleteEdge],
    );

    const [dragPositions, setDragPositions] = useState<
        Record<string, { x: number; y: number }>
    >({});
    const renderedNodes = useMemo(
        () =>
            reactNodes.map((node) => ({
                ...node,
                position: dragPositions[node.id] ?? node.position,
            })),
        [dragPositions, reactNodes],
    );

    const handleNodesChange = (changes: NodeChange<FlowReactNode>[]) => {
        const positions = changes.reduce<
            Record<string, { x: number; y: number }>
        >((current, change) => {
            if (change.type === 'position' && change.position !== undefined) {
                current[change.id] = change.position;
            }

            return current;
        }, {});
        if (Object.keys(positions).length > 0) {
            setDragPositions((current) => ({ ...current, ...positions }));
        }
    };

    const handleConnect = (connection: Connection) => {
        if (
            editable &&
            connection.source !== null &&
            connection.target !== null &&
            connection.source !== connection.target
        ) {
            onConnect(connection.source, connection.target);
        }
    };

    const handleNodeDragStop = (
        _event: MouseEvent | TouchEvent,
        node: FlowReactNode,
    ) => {
        if (editable) {
            onMoveNode(node.id, node.position);
            setDragPositions((current) => {
                const next = { ...current };
                delete next[node.id];
                return next;
            });
        }
    };

    return (
        <div className="bg-muted/30 relative min-h-[620px] flex-1 overflow-hidden">
            <ReactFlow
                nodes={renderedNodes}
                edges={reactEdges}
                onNodesChange={handleNodesChange}
                onEdgesChange={() => undefined}
                nodeTypes={nodeTypes}
                edgeTypes={edgeTypes}
                fitView={nodes.length > 0}
                fitViewOptions={{ padding: 0.25, maxZoom: 1.05 }}
                nodesDraggable={editable}
                nodesConnectable={editable}
                edgesReconnectable={false}
                deleteKeyCode={null}
                elementsSelectable
                panOnDrag
                onConnect={handleConnect}
                onNodeClick={(_event, node) => onSelectNode(node.id)}
                onNodeDragStop={handleNodeDragStop}
                proOptions={{ hideAttribution: true }}
                className="[&_.react-flow__controls-button]:bg-background [&_.react-flow__controls-button]:text-foreground [&_.react-flow__controls-button]:fill-foreground [&_.react-flow__controls-button]:border-border [&_.react-flow__controls]:overflow-hidden [&_.react-flow__controls]:rounded-xl [&_.react-flow__controls]:border [&_.react-flow__controls]:shadow-md"
            >
                <Background color="var(--ring)" gap={18} size={1} />
                <Controls
                    position="bottom-left"
                    showInteractive={false}
                    className="!bottom-5 !left-5"
                />
            </ReactFlow>

            {nodes.length === 0 && (
                <div className="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-[18px]">
                    <button
                        type="button"
                        onClick={onChooseTrigger}
                        className="border-border bg-card pointer-events-auto flex w-[230px] flex-col items-center gap-2 rounded-xl border-[1.5px] border-dashed p-5"
                    >
                        <span className="bg-muted flex size-[38px] items-center justify-center rounded-[10px]">
                            <Zap className="text-signal-attention size-[19px]" />
                        </span>
                        <span className="text-[13.5px] font-semibold">
                            Escolha o gatilho
                        </span>
                        <span className="text-muted-foreground text-center text-[11.5px] leading-[1.45]">
                            O que faz este fluxo começar
                        </span>
                    </button>

                    <svg
                        width="2"
                        height="30"
                        viewBox="0 0 2 30"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M1 0v30"
                            stroke="var(--border)"
                            strokeWidth="1.5"
                            strokeDasharray="3 3"
                        />
                    </svg>

                    <button
                        type="button"
                        onClick={onChooseTrigger}
                        aria-label="Escolher gatilho"
                        className="border-border text-muted-foreground bg-card pointer-events-auto flex size-[26px] items-center justify-center rounded-full border border-dashed"
                    >
                        <Plus className="size-[13px]" />
                    </button>

                    <p className="text-muted-foreground mt-1 max-w-[280px] text-center text-xs leading-[1.55]">
                        Um fluxo sempre começa por um gatilho. Escolha um — ou
                        descreva o atendimento no painel ao lado e deixe a IA
                        montar.
                    </p>
                </div>
            )}
        </div>
    );
}

function FlowNodeCard({ data, selected }: NodeProps<FlowReactNode>) {
    const node = data.domainNode;
    const isTrigger = node.type === 'trigger';

    return (
        <div
            className={cn(
                'bg-card border-border relative flex min-h-[74px] w-[190px] cursor-grab flex-col gap-[3px] rounded-[10px] border p-[11px_13px] text-left shadow-sm transition-[box-shadow,border-color] active:cursor-grabbing',
                isTrigger && 'border-l-[3px]',
                selected && 'ring-foreground ring-2 ring-offset-0',
                data.traceActive &&
                    'border-signal-live shadow-[0_0_0_4px_color-mix(in_oklch,var(--signal-live)_18%,transparent)]',
            )}
            style={isTrigger ? { borderLeftColor: data.accent } : undefined}
        >
            <Handle
                type="target"
                position={Position.Left}
                id="target"
                isConnectable={data.editable}
                className="!bg-muted-foreground !border-background !size-2.5 !border-2"
            />
            <Handle
                type="source"
                position={Position.Right}
                id="source"
                isConnectable={data.editable}
                className="!bg-primary !border-background !size-2.5 !border-2"
            />
            {data.editable && (
                <button
                    type="button"
                    className="nodrag nopan bg-background text-muted-foreground hover:text-destructive absolute -top-2 -right-2 flex size-5 items-center justify-center rounded-full border shadow-sm"
                    aria-label={`Excluir ${data.label}`}
                    onClick={(event) => {
                        event.stopPropagation();
                        data.onDeleteNode(node.id);
                    }}
                >
                    <X className="size-3" />
                </button>
            )}
            {data.editable && (
                <button
                    type="button"
                    className="nodrag nopan border-border bg-background text-muted-foreground hover:text-foreground absolute top-1/2 -right-[11px] z-10 flex size-[22px] -translate-y-1/2 items-center justify-center rounded-full border shadow-sm"
                    aria-label={`Adicionar depois de ${data.label}`}
                    onClick={(event) => {
                        event.stopPropagation();
                        data.onAddAfter(node.id);
                    }}
                >
                    <Plus className="size-3" />
                </button>
            )}
            <span
                className="flex items-center gap-1.5"
                style={{ color: data.accent }}
            >
                <data.Icon className="size-[13px]" strokeWidth={2.2} />
                <span className="text-[9px] font-bold tracking-[0.06em] uppercase">
                    {data.category}
                </span>
            </span>
            <span className="text-[13px] leading-[1.25] font-semibold">
                {data.label}
            </span>
            <span className="text-muted-foreground truncate text-[11px] leading-[1.3]">
                {isTrigger ? 'Ponto de entrada' : summaryFor(node)}
            </span>
        </div>
    );
}

function FlowEdgeLine({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    markerEnd,
    data,
}: EdgeProps<FlowReactEdge>) {
    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    });

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                markerEnd={markerEnd}
                interactionWidth={24}
                className="!stroke-muted-foreground/45"
                style={{ strokeWidth: 1.5 }}
            />
            <EdgeLabelRenderer>
                <div
                    className="nodrag nopan pointer-events-auto absolute flex -translate-x-1/2 -translate-y-1/2 items-center gap-1"
                    style={{
                        transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
                    }}
                >
                    {data?.label && (
                        <span className="bg-background/90 text-muted-foreground rounded px-1.5 py-0.5 text-[11px] shadow-sm">
                            {data.label}
                        </span>
                    )}
                    <button
                        type="button"
                        className="bg-background text-muted-foreground hover:text-destructive flex size-5 items-center justify-center rounded-full border text-xs shadow-sm"
                        aria-label="Desconectar aresta"
                        onClick={(event) => {
                            event.stopPropagation();
                            data?.onDeleteEdge(data.domainEdge);
                        }}
                    >
                        ×
                    </button>
                </div>
            </EdgeLabelRenderer>
        </>
    );
}

function summaryFor(node: FlowNode): string {
    const config = node.config;
    const value =
        config.text ??
        config.question ??
        config.instruction ??
        config.url ??
        config.variable;
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return 'Clique para configurar este passo.';
}
