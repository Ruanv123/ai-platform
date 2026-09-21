import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { channelIcons, type ChannelTypeOption } from './channel-catalog';
import { WhatsAppConnectionForm } from './whatsapp-connection-form';
import { WhatsAppTestConnectionForm } from './whatsapp-test-connection-form';

type AgentOption = {
    id: string;
    name: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    channelTypes: ChannelTypeOption[];
    agents: AgentOption[];
    canManage: boolean;
};

const typeLabels: Record<string, string> = {
    whatsapp: 'Conta da Meta e Número do WhatsApp',
    whatsapp_test: 'Conexão com o serviço de testes',
};

/** Creation flow for a Canal: pick a Tipo, then fill its configuration, both inside this modal. */
export function ChannelCreateDialog({
    open,
    onOpenChange,
    channelTypes,
    agents,
    canManage,
}: Props) {
    const [step, setStep] = useState<'type' | 'config'>('type');
    const [selectedType, setSelectedType] = useState<string | null>(null);

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setStep('type');
            setSelectedType(null);
        }
    };

    const selectableTypes = channelTypes.filter((type) => !type.configured);
    const selected = channelTypes.find((type) => type.value === selectedType);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                {step === 'type' ? (
                    <>
                        <DialogHeader>
                            <DialogTitle>Adicionar canal</DialogTitle>
                            <DialogDescription>
                                Escolha por onde sua Empresa vai atender.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {selectableTypes.map((type) => {
                                const Icon = channelIcons[type.icon];
                                const isSelected = type.value === selectedType;

                                return (
                                    <button
                                        key={type.value}
                                        type="button"
                                        disabled={!type.available}
                                        aria-pressed={isSelected}
                                        onClick={() =>
                                            setSelectedType(type.value)
                                        }
                                        className={cn(
                                            'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
                                            type.available
                                                ? 'hover:bg-accent cursor-pointer'
                                                : 'cursor-not-allowed opacity-60',
                                            isSelected &&
                                                'border-primary ring-primary/20 ring-2',
                                        )}
                                    >
                                        <div className="flex w-full items-start justify-between gap-2">
                                            <span className="bg-muted flex size-8 shrink-0 items-center justify-center rounded-md">
                                                <Icon className="size-4" />
                                            </span>
                                            {!type.available && (
                                                <Badge variant="secondary">
                                                    Em breve
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="grid gap-0.5">
                                            <p className="text-sm font-medium">
                                                {type.label}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {type.description}
                                            </p>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                disabled={selectedType === null}
                                onClick={() => setStep('config')}
                            >
                                Continuar
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>{selected?.label}</DialogTitle>
                            <DialogDescription>
                                {selectedType !== null
                                    ? typeLabels[selectedType]
                                    : null}
                            </DialogDescription>
                        </DialogHeader>

                        {selectedType === 'whatsapp' && (
                            <WhatsAppConnectionForm
                                whatsapp={null}
                                agents={agents}
                                canManage={canManage}
                                onSuccess={() => handleOpenChange(false)}
                            />
                        )}

                        {selectedType === 'whatsapp_test' && (
                            <WhatsAppTestConnectionForm
                                agents={agents}
                                canManage={canManage}
                                onSuccess={() => handleOpenChange(false)}
                            />
                        )}

                        <div className="flex justify-start">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setStep('type')}
                            >
                                Voltar
                            </Button>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
