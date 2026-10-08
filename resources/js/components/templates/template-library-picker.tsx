import { Bot, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { categoryOptions } from './template-draft';
import type { LibraryModel } from './template-draft';

export default function TemplateLibraryPicker({
    models,
    selectedKey,
    onUse,
}: {
    models: LibraryModel[];
    selectedKey: string | null;
    onUse: (model: LibraryModel) => void;
}) {
    return (
        <section className="grid gap-3">
            <div className="grid gap-1">
                <h2 className="flex items-center gap-2 font-semibold">
                    <Sparkles className="size-4" />
                    Começar de um modelo
                </h2>
                <p className="text-muted-foreground text-sm">
                    Modelos prontos para o Agente de IA usar. Você revisa o
                    texto antes de enviar para a Meta.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {models.map((model) => (
                    <div
                        key={model.key}
                        className={cn(
                            'bg-card flex flex-col gap-2 rounded-lg border p-3.5 shadow-xs',
                            selectedKey === model.key &&
                                'border-primary ring-primary ring-1',
                        )}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <span className="text-sm font-semibold">
                                {model.title}
                            </span>
                            <Badge variant="outline">
                                {categoryOptions.find(
                                    (option) => option.value === model.category,
                                )?.label ?? model.category}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-[13px] leading-[18px]">
                            {model.purpose}
                        </p>
                        <p className="bg-muted/50 line-clamp-3 rounded-md p-2 text-xs leading-[18px] whitespace-pre-wrap">
                            {model.headerType === 'TEXT' && model.headerText
                                ? `${model.headerText}\n${model.body}`
                                : model.body}
                        </p>
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Bot className="size-3.5" />
                            {model.usageLabel}
                        </span>
                        <Button
                            type="button"
                            variant={
                                selectedKey === model.key
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            className="mt-auto self-start"
                            onClick={() => onUse(model)}
                        >
                            {selectedKey === model.key
                                ? 'Modelo em uso'
                                : 'Usar modelo'}
                        </Button>
                    </div>
                ))}
            </div>
        </section>
    );
}
