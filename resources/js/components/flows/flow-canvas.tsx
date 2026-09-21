import {
    Bot,
    CircleStop,
    Clock3,
    GitBranch,
    Globe2,
    LayoutTemplate,
    MessageSquare,
    MoreHorizontal,
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

const icons = {
    Bot,
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

function iconFor(type: string, catalog: NodeCatalogItem[]) {
    const name = catalog.find((item) => item.type === type)?.icon;

    return (
        (icons as Record<string, typeof MessageSquare>)[name ?? ''] ??
        fallbackIcon
    );
}

function labelFor(type: string, catalog: NodeCatalogItem[]) {
    return catalog.find((item) => item.type === type)?.label ?? type;
}

function positionFor(node: FlowNode, index: number) {
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
    onStartConnection: (nodeId: string) => void;
    onFinishConnection: (nodeId: string) => void;
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
    onStartConnection,
    onFinishConnection,
    onDeleteEdge,
    onChooseTrigger,
    editable = true,
}: FlowCanvasProps) {
    const [drag, setDrag] = useState<{
        nodeId: string;
        offsetX: number;
        offsetY: number;
        position: { x: number; y: number };
    } | null>(null);
    const [zoom, setZoom] = useState(1);
    const [connectionSource, setConnectionSource] = useState<string | null>(
        null,
    );
    const positions = useMemo(
        () =>
            new Map(
                nodes.map((node, index) => [node.id, positionFor(node, index)]),
            ),
        [nodes],
    );

    const beginDrag = (
        event: React.PointerEvent<HTMLDivElement>,
        node: FlowNode,
        index: number,
    ) => {
        if ((event.target as HTMLElement).closest('button')) {
            return;
        }
        const position = positionFor(node, index);
        setDrag({
            nodeId: node.id,
            offsetX: event.clientX - position.x * zoom,
            offsetY: event.clientY - position.y * zoom,
            position,
        });
        event.currentTarget.setPointerCapture(event.pointerId);
    };

    const moveDrag = (event: React.PointerEvent<HTMLDivElement>) => {
        if (drag === null) {
            return;
        }
        const position = {
            x: Math.max(24, (event.clientX - drag.offsetX) / zoom),
            y: Math.max(104, (event.clientY - drag.offsetY) / zoom),
        };
        setDrag({ ...drag, position });
    };

    const finishDrag = () => {
        if (drag !== null) {
            onMoveNode(drag.nodeId, drag.position);
        }
        setDrag(null);
    };

    const visibleNodes = nodes.map((node, index) => ({
        node,
        index,
        position:
            drag?.nodeId === node.id ? drag.position : positionFor(node, index),
    }));

    return (
        <div
            className="relative min-h-[620px] flex-1 overflow-hidden bg-[radial-gradient(circle_at_center,oklch(0.98_0_0),oklch(0.94_0_0))] dark:bg-[radial-gradient(circle_at_center,oklch(0.21_0_0),oklch(0.145_0_0))]"
            onPointerMove={moveDrag}
            onPointerUp={finishDrag}
        >
            <div className="pointer-events-none absolute inset-0 [background-image:radial-gradient(oklch(0.6_0_0/.26)_1px,transparent_1px)] [background-size:24px_24px] opacity-60 dark:opacity-30" />

            <div className="absolute top-24 left-1/2 z-10 flex -translate-x-1/2 flex-col items-center gap-3 text-center">
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
                        <Button variant="outline" onClick={onChooseTrigger}>
                            <Plus />
                            Escolher gatilho
                        </Button>
                    </>
                )}
            </div>

            {nodes.length > 0 && (
                <div
                    className="absolute inset-0 origin-top-left"
                    style={{ transform: `scale(${zoom})` }}
                >
                    <svg className="pointer-events-none absolute inset-0 h-full w-full overflow-visible">
                        <defs>
                            <marker
                                id="flow-arrow"
                                markerHeight="8"
                                markerWidth="8"
                                orient="auto-start-reverse"
                                refX="7"
                                refY="4"
                                viewBox="0 0 8 8"
                            >
                                <path
                                    d="M 0 0 L 8 4 L 0 8 z"
                                    className="fill-muted-foreground/50"
                                />
                            </marker>
                        </defs>
                        {edges.map((edge) => {
                            const source = positions.get(edge.source);
                            const target = positions.get(edge.target);
                            if (source === undefined || target === undefined) {
                                return null;
                            }
                            const startX = source.x + 224;
                            const startY = source.y + 76;
                            const endX = target.x;
                            const endY = target.y + 76;
                            const curve = Math.max(44, (endX - startX) / 2);
                            return (
                                <g
                                    key={
                                        edge.id ??
                                        `${edge.source}-${edge.target}`
                                    }
                                >
                                    <path
                                        d={`M ${startX} ${startY} C ${startX + curve} ${startY}, ${endX - curve} ${endY}, ${endX} ${endY}`}
                                        fill="none"
                                        markerEnd="url(#flow-arrow)"
                                        stroke="currentColor"
                                        strokeDasharray={
                                            edge.label ? '5 4' : undefined
                                        }
                                        className="text-muted-foreground/45"
                                        strokeWidth="1.5"
                                    />
                                    {edge.label && (
                                        <text
                                            x={(startX + endX) / 2}
                                            y={(startY + endY) / 2 - 8}
                                            className="fill-muted-foreground text-[11px]"
                                            textAnchor="middle"
                                        >
                                            {edge.label}
                                        </text>
                                    )}
                                    <foreignObject
                                        x={(startX + endX) / 2 - 10}
                                        y={(startY + endY) / 2 - 10}
                                        width="20"
                                        height="20"
                                        className="pointer-events-auto overflow-visible"
                                    >
                                        <button
                                            type="button"
                                            className="bg-background text-muted-foreground hover:text-destructive flex size-5 items-center justify-center rounded-full border text-xs shadow-sm"
                                            aria-label="Desconectar aresta"
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onDeleteEdge(edge);
                                            }}
                                        >
                                            ×
                                        </button>
                                    </foreignObject>
                                </g>
                            );
                        })}
                    </svg>

                    {visibleNodes.map(({ node, index, position }) => {
                        const Icon = iconFor(node.type, catalog);
                        const isTrigger = node.type === 'trigger';
                        const isTraceActive = traceNodeIds.includes(node.id);
                        const isSelected = selectedNodeId === node.id;
                        const isConnecting = connectionSource === node.id;
                        return (
                            <div
                                key={node.id}
                                className={cn(
                                    'bg-card absolute flex h-[152px] w-[224px] cursor-grab touch-none flex-col rounded-2xl border p-4 shadow-sm transition-[box-shadow,transform,border-color] active:cursor-grabbing',
                                    isTrigger
                                        ? 'border-amber-500/60 bg-amber-500/[0.06]'
                                        : 'border-border/80',
                                    isSelected &&
                                        'border-primary ring-primary/15 shadow-lg ring-2',
                                    isTraceActive &&
                                        'border-signal-live shadow-[0_0_0_4px_color-mix(in_oklch,var(--signal-live)_18%,transparent)]',
                                    isConnecting && 'ring-2 ring-amber-500/50',
                                )}
                                style={{ left: position.x, top: position.y }}
                                onPointerDown={(event) =>
                                    beginDrag(event, node, index)
                                }
                                onClick={() => {
                                    if (
                                        connectionSource !== null &&
                                        connectionSource !== node.id
                                    ) {
                                        onFinishConnection(node.id);
                                        setConnectionSource(null);
                                    } else {
                                        onSelectNode(node.id);
                                    }
                                }}
                            >
                                {editable && (
                                    <>
                                        <button
                                            type="button"
                                            className="bg-background text-muted-foreground absolute -top-2 left-1/2 flex size-4 items-center justify-center rounded-full border"
                                            aria-label={`Conectar antes de ${labelFor(node.type, catalog)}`}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onFinishConnection(node.id);
                                                setConnectionSource(null);
                                            }}
                                        >
                                            <span className="size-1 rounded-full bg-current" />
                                        </button>
                                        <button
                                            type="button"
                                            className="bg-background text-muted-foreground hover:text-foreground absolute -top-2 right-2 flex size-5 items-center justify-center rounded-full border"
                                            aria-label={`Excluir ${labelFor(node.type, catalog)}`}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onDeleteNode(node.id);
                                            }}
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </>
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
                                        <Icon className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">
                                            {labelFor(node.type, catalog)}
                                        </p>
                                        <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">
                                            {isTrigger
                                                ? 'Ponto de entrada'
                                                : summaryFor(node)}
                                        </p>
                                    </div>
                                </div>
                                {editable && (
                                    <>
                                        <div className="mt-auto flex items-center justify-between gap-2">
                                            <button
                                                type="button"
                                                className="text-muted-foreground hover:text-foreground inline-flex min-h-8 items-center gap-1 rounded-md px-2 text-xs transition-colors"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    onAddAfter(node.id);
                                                }}
                                            >
                                                <Plus className="size-3.5" />
                                                Próximo
                                            </button>
                                            <button
                                                type="button"
                                                className="text-muted-foreground hover:text-foreground inline-flex min-h-8 items-center gap-1 rounded-md px-2 text-xs transition-colors"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setConnectionSource(
                                                        node.id,
                                                    );
                                                    onStartConnection(node.id);
                                                }}
                                            >
                                                <MoreHorizontal className="size-3.5" />
                                                Ligar
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            className="bg-background text-muted-foreground hover:text-foreground absolute top-1/2 -right-2 flex size-4 -translate-y-1/2 items-center justify-center rounded-full border"
                                            aria-label={`Adicionar depois de ${labelFor(node.type, catalog)}`}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                onAddAfter(node.id);
                                            }}
                                        >
                                            <Plus className="size-3" />
                                        </button>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="bg-background/90 absolute right-5 bottom-5 z-20 flex items-center gap-1 rounded-xl border p-1 shadow-sm backdrop-blur">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Diminuir zoom"
                    onClick={() =>
                        setZoom((value) => Math.max(0.65, value - 0.1))
                    }
                >
                    −
                </Button>
                <span className="text-muted-foreground min-w-12 text-center text-xs tabular-nums">
                    {Math.round(zoom * 100)}%
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Aumentar zoom"
                    onClick={() =>
                        setZoom((value) => Math.min(1.4, value + 0.1))
                    }
                >
                    +
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Enquadrar grafo"
                    onClick={() => setZoom(1)}
                >
                    <Workflow />
                </Button>
            </div>
        </div>
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
