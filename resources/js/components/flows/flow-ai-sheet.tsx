import { Check, ChevronRight, LoaderCircle, Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apply, propose } from '@/routes/flows/ai';
import { postJson } from '@/lib/flow-http';
import type { FlowNode, FlowEdge } from '@/components/flows/flow-canvas';

type FlowAiSheetProps = {
    flowId: string;
    open: boolean;
    onClose: () => void;
    onApply: (dsl: {
        version: number;
        nodes: FlowNode[];
        edges: FlowEdge[];
    }) => void;
};

type Change = {
    kind: string;
    description: string;
};

type AiResponse = {
    dsl: { version: number; nodes: FlowNode[]; edges: FlowEdge[] };
    changes: Change[];
    assistantMessage: string;
};

export function FlowAiSheet({
    flowId,
    open,
    onClose,
    onApply,
}: FlowAiSheetProps) {
    const [message, setMessage] = useState('');
    const [history, setHistory] = useState<
        Array<{ role: 'user' | 'assistant'; content: string }>
    >([]);
    const [proposal, setProposal] = useState<AiResponse | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!open) {
        return null;
    }

    const ask = async () => {
        const trimmedMessage = message.trim();
        if (trimmedMessage === '' || processing) {
            return;
        }
        setProcessing(true);
        setError(null);
        try {
            const response = await postJson<AiResponse>(propose(flowId).url, {
                message: trimmedMessage,
                history,
            });
            setProposal(response);
            setHistory((current) => [
                ...current,
                { role: 'user', content: trimmedMessage },
                { role: 'assistant', content: response.assistantMessage },
            ]);
            setMessage('');
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : 'A IA não respondeu.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const applyProposal = async () => {
        if (proposal === null || processing) {
            return;
        }
        setProcessing(true);
        setError(null);
        try {
            await postJson(apply(flowId).url, { dsl: proposal.dsl });
            onApply(proposal.dsl);
            setProposal(null);
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : 'Não foi possível aplicar a proposta.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <aside className="bg-background absolute inset-y-0 right-0 z-40 flex w-[min(420px,100%)] flex-col border-l">
            <header className="flex items-start justify-between gap-4 border-b p-5">
                <div className="flex items-start gap-3">
                    <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl">
                        <Sparkles className="size-4" />
                    </span>
                    <div>
                        <h2 className="font-semibold tracking-tight">
                            Criar com IA
                        </h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            A proposta só entra no desenho depois da sua
                            aprovação.
                        </p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar chat de IA"
                    onClick={onClose}
                >
                    <X />
                </Button>
            </header>
            <div className="flex-1 space-y-4 overflow-y-auto p-5">
                {history.length === 0 && proposal === null && (
                    <div className="border-border/70 bg-muted/30 rounded-xl border border-dashed p-4 text-sm leading-6">
                        Descreva o atendimento como você explicaria para uma
                        pessoa da equipe. Por exemplo: “pergunte o CPF, consulte
                        nossa API e transfira para humano se não encontrar”.
                    </div>
                )}
                {history.map((item, index) => (
                    <div
                        key={`${item.role}-${index}`}
                        className={item.role === 'user' ? 'ml-8' : 'mr-8'}
                    >
                        <p className="text-muted-foreground mb-1 text-[11px] font-medium tracking-[0.14em] uppercase">
                            {item.role === 'user' ? 'Você' : 'Fluxo'}
                        </p>
                        <p className="bg-muted/60 rounded-xl px-3 py-2 text-sm leading-6">
                            {item.content}
                        </p>
                    </div>
                ))}
                {proposal !== null && (
                    <section className="border-primary/30 bg-primary/[0.04] rounded-xl border p-4">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <Check className="text-primary size-4" />
                            Alterações para revisar
                        </div>
                        <ul className="mt-3 grid gap-2">
                            {proposal.changes.map((change, index) => (
                                <li
                                    key={`${change.kind}-${index}`}
                                    className="flex items-start gap-2 text-sm leading-5"
                                >
                                    <ChevronRight className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                    {change.description}
                                </li>
                            ))}
                        </ul>
                        <div className="mt-4 flex gap-2">
                            <Button
                                className="flex-1"
                                onClick={applyProposal}
                                disabled={processing}
                            >
                                {processing ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Check />
                                )}
                                Aplicar ao rascunho
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setProposal(null)}
                                disabled={processing}
                            >
                                Descartar
                            </Button>
                        </div>
                    </section>
                )}
                {error && (
                    <p className="text-destructive border-destructive/30 rounded-lg border px-3 py-2 text-sm">
                        {error}
                    </p>
                )}
            </div>
            <div className="border-t p-4">
                <div className="flex items-end gap-2">
                    <Input
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                void ask();
                            }
                        }}
                        placeholder="Peça uma alteração..."
                        disabled={processing}
                    />
                    <Button
                        size="icon"
                        aria-label="Enviar pedido para a IA"
                        onClick={() => void ask()}
                        disabled={processing || message.trim() === ''}
                    >
                        {processing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <ChevronRight />
                        )}
                    </Button>
                </div>
                <p className="text-muted-foreground mt-2 text-[11px]">
                    A IA vê o rascunho atual e mantém o que você não pediu para
                    mudar.
                </p>
            </div>
        </aside>
    );
}
