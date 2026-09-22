import { ChevronRight, FlaskConical, LoaderCircle, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { playground } from '@/routes/flows';
import { postJson } from '@/lib/flow-http';

type TraceItem = {
    nodeId: string;
    type: string;
    result: string;
    status: string;
};

type PlaygroundResult = {
    trace: TraceItem[];
    outputs: string[];
    tokens: { input: number; output: number; total: number };
    costInReais: number;
    note: string;
};

type FlowPlaygroundPanelProps = {
    flowId: string;
    open: boolean;
    onClose: () => void;
    onTrace: (nodeIds: string[]) => void;
    triggerType: string;
};

export function FlowPlaygroundPanel({
    flowId,
    open,
    onClose,
    onTrace,
    triggerType,
}: FlowPlaygroundPanelProps) {
    const [message, setMessage] = useState('Olá');
    const [payload, setPayload] = useState('{}');
    const [result, setResult] = useState<PlaygroundResult | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!open) {
        return null;
    }

    const run = async () => {
        if (processing) {
            return;
        }
        setProcessing(true);
        setError(null);
        try {
            let parsedPayload: Record<string, unknown> = {};
            if (triggerType === 'webhook') {
                try {
                    parsedPayload = JSON.parse(payload) as Record<
                        string,
                        unknown
                    >;
                } catch {
                    throw new Error(
                        'O payload falso precisa ser um JSON válido.',
                    );
                }
            }
            const response = await postJson<PlaygroundResult>(
                playground(flowId).url,
                { message, payload: parsedPayload },
            );
            setResult(response);
            onTrace(response.trace.map((item) => item.nodeId));
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : 'Não foi possível testar o Fluxo.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <section className="bg-background absolute inset-x-0 bottom-0 z-35 flex h-[min(310px,45%)] min-h-[250px] flex-col border-t">
            <header className="flex items-center justify-between gap-4 border-b px-5 py-3">
                <div className="flex items-center gap-3">
                    <span className="bg-signal-attention/15 text-signal-attention flex size-8 items-center justify-center rounded-lg">
                        <FlaskConical className="size-4" />
                    </span>
                    <div>
                        <h2 className="text-sm font-semibold">Playground</h2>
                        <p className="text-muted-foreground text-xs">
                            Rascunho local · nada é enviado ao Contato
                        </p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar Playground"
                    onClick={onClose}
                >
                    <X />
                </Button>
            </header>
            <div className="grid min-h-0 flex-1 md:grid-cols-[1fr_1fr]">
                <div className="flex min-h-0 flex-col border-r p-5">
                    <p className="text-muted-foreground mb-3 text-[11px] font-medium tracking-[0.14em] uppercase">
                        Conversa de teste
                    </p>
                    <div className="bg-muted/40 flex-1 rounded-xl p-4 text-sm leading-6">
                        <span className="text-muted-foreground text-xs">
                            Você
                        </span>
                        <p className="mt-1">
                            {message || 'Escreva uma mensagem para começar.'}
                        </p>
                        {result?.outputs.map((output, index) => (
                            <div
                                key={`${output}-${index}`}
                                className="bg-primary/10 mt-4 rounded-xl px-3 py-2"
                            >
                                <span className="text-muted-foreground text-xs">
                                    Fluxo
                                </span>
                                <p className="mt-1">{output}</p>
                            </div>
                        ))}
                    </div>
                    {triggerType === 'webhook' && (
                        <textarea
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 mt-3 min-h-16 w-full resize-y rounded-md border bg-transparent px-3 py-2 font-mono text-xs outline-none focus-visible:ring-[3px]"
                            value={payload}
                            onChange={(event) => setPayload(event.target.value)}
                            aria-label="Payload falso do webhook"
                            placeholder='{"phone":"5511999999999"}'
                        />
                    )}
                    <div className="mt-3 flex gap-2">
                        <Input
                            value={message}
                            onChange={(event) => setMessage(event.target.value)}
                            placeholder="Mensagem de teste"
                        />
                        <Button
                            size="icon"
                            aria-label="Executar teste"
                            onClick={() => void run()}
                            disabled={processing}
                        >
                            {processing ? (
                                <LoaderCircle className="animate-spin" />
                            ) : (
                                <ChevronRight />
                            )}
                        </Button>
                    </div>
                </div>
                <div className="flex min-h-0 flex-col p-5">
                    <p className="text-muted-foreground mb-3 text-[11px] font-medium tracking-[0.14em] uppercase">
                        Traço do turno
                    </p>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {result === null ? (
                            <div className="text-muted-foreground flex h-full items-center justify-center text-center text-sm">
                                Execute um turno para acender os Nós por onde o
                                Fluxo passou.
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                {result.trace.map((item, index) => (
                                    <div
                                        key={`${item.nodeId}-${index}`}
                                        className="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <span className="bg-signal-live size-2 rounded-full shadow-[0_0_0_4px_color-mix(in_oklch,var(--signal-live)_18%,transparent)]" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {item.nodeId}
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {item.result}
                                        </span>
                                    </div>
                                ))}
                                <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <div className="bg-muted/50 rounded-lg p-3">
                                        <span className="text-muted-foreground">
                                            Tokens
                                        </span>
                                        <strong className="mt-1 block tabular-nums">
                                            {result.tokens.total}
                                        </strong>
                                    </div>
                                    <div className="bg-muted/50 rounded-lg p-3">
                                        <span className="text-muted-foreground">
                                            Custo estimado
                                        </span>
                                        <strong className="mt-1 block tabular-nums">
                                            R$ {result.costInReais.toFixed(4)}
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                    {error && (
                        <p className="text-destructive mt-3 text-xs">{error}</p>
                    )}
                    {result && (
                        <p className="text-muted-foreground mt-3 text-[11px]">
                            {result.note}
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}
