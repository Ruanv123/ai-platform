import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function VariablesPanel({
    idPrefix,
    positions,
    examples,
    descriptions,
    counter,
    onExampleChange,
    onDescriptionChange,
    onRemove,
}: {
    idPrefix: string;
    positions: number[];
    examples: Record<number, string>;
    descriptions: Record<number, string>;
    counter: string;
    onExampleChange: (position: number, example: string) => void;
    onDescriptionChange: (position: number, description: string) => void;
    onRemove: (position: number) => void;
}) {
    return (
        <div className="bg-muted/50 grid gap-2.5 border-t p-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="text-[13px] font-semibold">Variáveis</span>
                <span className="text-muted-foreground text-xs">{counter}</span>
            </div>
            {positions.length === 0 ? (
                <p className="text-muted-foreground text-[13px]">
                    Use “Variável” para personalizar com dados do Contato, como
                    nome ou data.
                </p>
            ) : (
                <>
                    {positions.map((position) => {
                        const example = examples[position] ?? '';

                        return (
                            <div
                                key={position}
                                className="flex flex-wrap items-center gap-x-3 gap-y-2"
                            >
                                <span className="bg-background min-w-11 rounded-md border px-2 py-0.5 text-center text-[13px] font-semibold">{`{{${position}}}`}</span>
                                <Input
                                    id={`${idPrefix}-description-${position}`}
                                    aria-label={`O que é a variável ${position}`}
                                    value={descriptions[position] ?? ''}
                                    maxLength={255}
                                    onChange={(event) =>
                                        onDescriptionChange(
                                            position,
                                            event.target.value,
                                        )
                                    }
                                    placeholder="O que é? Ex.: primeiro nome do Contato"
                                    className="bg-background min-w-0 flex-1 basis-52"
                                />
                                <Input
                                    id={`${idPrefix}-example-${position}`}
                                    aria-label={`Exemplo da variável ${position}`}
                                    value={example}
                                    onChange={(event) =>
                                        onExampleChange(
                                            position,
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Exemplo: Maria"
                                    aria-invalid={example.trim() === ''}
                                    className="bg-background min-w-0 flex-1 basis-32"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remover variável ${position}`}
                                    onClick={() => onRemove(position)}
                                >
                                    <X />
                                </Button>
                            </div>
                        );
                    })}
                    <p className="text-muted-foreground text-xs">
                        O exemplo vai para a análise da Meta. A descrição ensina
                        o Agente de IA a preencher a variável no envio.
                    </p>
                </>
            )}
        </div>
    );
}
