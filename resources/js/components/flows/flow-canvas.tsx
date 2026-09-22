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
import { Button } from '@/components/ui/button';
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

function positionFor(node: FlowNode, index: number): { x: number; y: number } {
    return node.position ?? { x: 96 + index * 300, y: 190 };
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
        <div className="relative min-h-[620px] flex-1 overflow-hidden bg-[radial-gradient(circle_at_center,oklch(0.98_0_0),oklch(0.94_0_0))] dark:bg-[radial-gradient(circle_at_center,oklch(0.21_0_0),oklch(0.145_0_0))]">
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
                className="[&_.react-flow__controls-button]:bg-background [&_.react-flow__controls-button]:text-foreground [&_.react-flow__controls-button]:fill-foreground [&_.react-flow__controls-button]:border-border [&_.react-flow__controls]:overflow-hidden [&_.react-flow__controls]:rounded-xl [&_.react-flow__controls]:border [&_.react-flow__controls]:shadow-sm"
            >
                <Background
                    color="var(--muted-foreground)"
                    gap={24}
                    size={1}
                    className="opacity-25 dark:opacity-15"
                />
                <Controls
                    position="bottom-right"
                    showInteractive={false}
                    className="!right-5 !bottom-5"
                />
            </ReactFlow>

            <div className="pointer-events-none absolute top-24 left-1/2 z-10 flex -translate-x-1/2 flex-col items-center gap-3 text-center">
                {nodes.length === 0 && (
                    <>
                        <div className="flex size-16 items-center justify-center rounded-2xl border border-dashed border-amber-500/70 bg-amber-500/10 text-amber-600 dark:text-amber-300">
                            <Zap className="size-7" />
                        </div>
                        <div>
                            <p className="font-semibold tracking-tight">
                                Escolha o gatilho
                            </p>
                            <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                Todo atendimento começa por aqui. Adicione o
                                primeiro passo para sair da tela em branco.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            className="pointer-events-auto"
                            onClick={onChooseTrigger}
                        >
                            <Plus />
                            Escolher gatilho
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

function FlowNodeCard({ data, selected }: NodeProps<FlowReactNode>) {
    const node = data.domainNode;
    const isTrigger = node.type === 'trigger';

    return (
        <div
            className={cn(
                'bg-card relative flex h-[152px] w-[224px] cursor-grab flex-col rounded-2xl border p-4 shadow-sm transition-[box-shadow,border-color] active:cursor-grabbing',
                isTrigger
                    ? 'border-amber-500/60 bg-amber-500/[0.06]'
                    : 'border-border/80',
                selected && 'border-primary ring-primary/15 shadow-lg ring-2',
                data.traceActive &&
                    'border-signal-live shadow-[0_0_0_4px_color-mix(in_oklch,var(--signal-live)_18%,transparent)]',
            )}
        >
            <Handle
                type="target"
                position={Position.Left}
                id="target"
                isConnectable={data.editable}
                className="!bg-muted-foreground !border-background !size-3 !border-2"
            />
            <Handle
                type="source"
                position={Position.Right}
                id="source"
                isConnectable={data.editable}
                className="!bg-primary !border-background !size-3 !border-2"
            />
            {data.editable && (
                <button
                    type="button"
                    className="nodrag nopan bg-background text-muted-foreground hover:text-destructive absolute -top-2 right-2 flex size-6 items-center justify-center rounded-full border shadow-sm"
                    aria-label={`Excluir ${data.label}`}
                    onClick={(event) => {
                        event.stopPropagation();
                        data.onDeleteNode(node.id);
                    }}
                >
                    <X className="size-3" />
                </button>
            )}
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-xl',
                        isTrigger
                            ? 'bg-amber-500/15 text-amber-600 dark:text-amber-300'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    <data.Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">
                        {data.label}
                    </p>
                    <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">
                        {isTrigger ? 'Ponto de entrada' : summaryFor(node)}
                    </p>
                </div>
            </div>
            {data.editable && (
                <button
                    type="button"
                    className="nodrag nopan text-muted-foreground hover:text-foreground mt-auto inline-flex min-h-8 w-fit items-center gap-1 rounded-md px-2 text-xs transition-colors"
                    onClick={(event) => {
                        event.stopPropagation();
                        data.onAddAfter(node.id);
                    }}
                >
                    <Plus className="size-3.5" />
                    Próximo
                </button>
            )}
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
