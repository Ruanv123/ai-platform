import { usePoll } from '@inertiajs/react';

type Props = {
    progress: number;
    processedCount: number;
    totalCount: number | null;
};

export default function ShopifyCatalogImportProgress({
    progress,
    processedCount,
    totalCount,
}: Props) {
    usePoll(3000, { only: ['shop'] });

    return (
        <section
            className="bg-muted/30 grid gap-3 rounded-lg p-4"
            aria-label="Progresso da importação do catálogo"
        >
            <div className="flex items-center justify-between gap-4">
                <p className="text-sm font-medium">Importando o catálogo</p>
                <span className="text-muted-foreground text-xs tabular-nums">
                    {progress}%
                </span>
            </div>
            <div
                role="progressbar"
                aria-label="Progresso da importação"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={progress}
                className="bg-muted h-2 overflow-hidden rounded-full"
            >
                <div
                    className="bg-primary h-full rounded-full transition-[width] duration-300 motion-reduce:transition-none"
                    style={{
                        width: `${Math.max(progress, processedCount > 0 ? 4 : 2)}%`,
                    }}
                />
            </div>
            <p aria-live="polite" className="text-muted-foreground text-xs">
                {processedCount === 0
                    ? 'A Shopify está preparando os dados do catálogo.'
                    : `${processedCount.toLocaleString('pt-BR')} registros processados${totalCount === null ? '' : ` de ${totalCount.toLocaleString('pt-BR')} previstos`}.`}
            </p>
        </section>
    );
}
