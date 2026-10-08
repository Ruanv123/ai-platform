import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { emptyButton } from './template-draft';
import type { ButtonDraft, ButtonType } from './template-draft';

export default function StandardButtonsField({
    buttons,
    error,
    onChange,
}: {
    buttons: ButtonDraft[];
    error?: string;
    onChange: (buttons: ButtonDraft[]) => void;
}) {
    const update = (key: string, changes: Partial<ButtonDraft>) =>
        onChange(
            buttons.map((button) =>
                button.key === key ? { ...button, ...changes } : button,
            ),
        );
    const hasCopyCode = buttons.some((button) => button.type === 'copy_code');

    return (
        <section className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Botões{' '}
                    <span className="text-muted-foreground font-normal">
                        (opcional · {buttons.length} de 3)
                    </span>
                </h3>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={buttons.length >= 3}
                    onClick={() => onChange([...buttons, emptyButton()])}
                >
                    <Plus /> Adicionar botão
                </Button>
            </div>
            {buttons.map((button, index) => (
                <div
                    key={button.key}
                    className="flex flex-wrap items-center gap-2"
                >
                    <select
                        aria-label={`Tipo do botão ${index + 1}`}
                        value={button.type}
                        onChange={(event) =>
                            update(button.key, {
                                type: event.target.value as ButtonType,
                            })
                        }
                        className="border-input bg-background h-9 w-40 rounded-md border px-3 text-sm"
                    >
                        <option value="quick_reply">Resposta rápida</option>
                        <option value="url">URL</option>
                        <option value="phone_number">Telefone</option>
                        <option
                            value="copy_code"
                            disabled={
                                hasCopyCode && button.type !== 'copy_code'
                            }
                        >
                            Copiar código
                        </option>
                    </select>
                    {button.type !== 'copy_code' && (
                        <Input
                            aria-label={`Texto do botão ${index + 1}`}
                            value={button.text}
                            maxLength={25}
                            onChange={(event) =>
                                update(button.key, { text: event.target.value })
                            }
                            placeholder="Texto do botão"
                            className="min-w-0 flex-1 basis-40"
                        />
                    )}
                    {button.type === 'url' && (
                        <Input
                            aria-label={`Endereço do botão ${index + 1}`}
                            value={button.url}
                            onChange={(event) =>
                                update(button.key, { url: event.target.value })
                            }
                            placeholder="https://exemplo.com/agenda"
                            className="min-w-0 flex-1 basis-52"
                        />
                    )}
                    {button.type === 'phone_number' && (
                        <Input
                            aria-label={`Telefone do botão ${index + 1}`}
                            value={button.phoneNumber}
                            onChange={(event) =>
                                update(button.key, {
                                    phoneNumber: event.target.value,
                                })
                            }
                            placeholder="+5511999999999"
                            className="min-w-0 flex-1 basis-52"
                        />
                    )}
                    {button.type === 'copy_code' && (
                        <Input
                            aria-label={`Código de exemplo do botão ${index + 1}`}
                            value={button.example}
                            maxLength={15}
                            onChange={(event) =>
                                update(button.key, {
                                    example: event.target.value,
                                })
                            }
                            placeholder="Código de exemplo, ex.: PROMO10"
                            className="min-w-0 flex-1 basis-52"
                        />
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Remover botão ${index + 1}`}
                        onClick={() =>
                            onChange(
                                buttons.filter(
                                    (candidate) => candidate.key !== button.key,
                                ),
                            )
                        }
                    >
                        <X />
                    </Button>
                    {button.type === 'url' && button.url.includes('{{') && (
                        <Input
                            aria-label={`Exemplo do endereço do botão ${index + 1}`}
                            value={button.example}
                            onChange={(event) =>
                                update(button.key, {
                                    example: event.target.value,
                                })
                            }
                            placeholder="Endereço completo de exemplo usado pela Meta"
                            className="basis-full"
                        />
                    )}
                </div>
            ))}
            {error && <p className="text-destructive text-xs">{error}</p>}
        </section>
    );
}
