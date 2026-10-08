import { Braces } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MediaDropzone from './media-dropzone';
import { buttonTypeLabels } from './template-draft';
import type { CardButtonType, CardDraft } from './template-draft';
import { nextVariablePosition, variablePositions } from './whatsapp-text';

export default function CarouselCardEditor({
    card,
    index,
    mediaKind,
    withBody,
    buttonTypes,
    errors,
    onChange,
}: {
    card: CardDraft;
    index: number;
    mediaKind: 'IMAGE' | 'VIDEO';
    withBody: boolean;
    buttonTypes: CardButtonType[];
    errors: Record<string, string>;
    onChange: (changes: Partial<CardDraft>) => void;
}) {
    const updateButton = (
        buttonIndex: number,
        changes: Partial<CardDraft['buttons'][number]>,
    ) =>
        onChange({
            buttons: buttonTypes.map((type, position) => {
                const current = card.buttons[position] ?? {
                    text: '',
                    url: '',
                    phoneNumber: '',
                    example: '',
                };

                return position === buttonIndex
                    ? { ...current, ...changes }
                    : current;
            }),
        });

    return (
        <div className="flex flex-wrap items-start gap-4">
            <div className="w-full sm:w-44">
                <MediaDropzone
                    key={`${card.key}-${mediaKind}`}
                    id={`card-${card.key}-media`}
                    kind={mediaKind}
                    file={card.media}
                    existingName={card.existingMediaName}
                    error={errors[`cards.${index}.media`]}
                    compact
                    onFileChange={(file) =>
                        onChange({
                            media: file,
                            existingMediaName: file
                                ? null
                                : card.existingMediaName,
                        })
                    }
                />
            </div>
            <div className="grid min-w-0 flex-1 basis-60 gap-3">
                {withBody && (
                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor={`card-${card.key}-body`}>
                                Texto do card
                            </Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onChange({
                                        body: `${card.body}${card.body === '' || /\s$/.test(card.body) ? '' : ' '}{{${nextVariablePosition(card.body)}}}`.slice(
                                            0,
                                            160,
                                        ),
                                    })
                                }
                            >
                                <Braces /> Variável
                            </Button>
                        </div>
                        <textarea
                            id={`card-${card.key}-body`}
                            value={card.body}
                            maxLength={160}
                            rows={3}
                            onChange={(event) =>
                                onChange({ body: event.target.value })
                            }
                            placeholder="Ex.: Peças leves para os dias quentes."
                            className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 resize-none rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                        <span className="text-muted-foreground text-xs">
                            {card.body.length}/160 caracteres
                        </span>
                        {variablePositions(card.body).map((position) => (
                            <div
                                key={position}
                                className="flex items-center gap-2"
                            >
                                <span className="bg-muted rounded-md border px-2 py-0.5 text-[13px] font-semibold">{`{{${position}}}`}</span>
                                <Input
                                    aria-label={`Exemplo da variável ${position} do card ${index + 1}`}
                                    value={card.bodyExamples[position] ?? ''}
                                    onChange={(event) =>
                                        onChange({
                                            bodyExamples: {
                                                ...card.bodyExamples,
                                                [position]: event.target.value,
                                            },
                                        })
                                    }
                                    placeholder="Exemplo usado pela Meta"
                                    className="min-w-0 flex-1"
                                />
                            </div>
                        ))}
                    </div>
                )}
                {buttonTypes.map((type, buttonIndex) => {
                    const button = card.buttons[buttonIndex];

                    return (
                        <div
                            key={buttonIndex}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="bg-muted text-muted-foreground inline-flex h-6 items-center rounded-md px-2 text-xs font-medium">
                                {buttonTypeLabels[type]}
                            </span>
                            <Input
                                aria-label={`Texto do botão ${buttonIndex + 1} do card ${index + 1}`}
                                value={button?.text ?? ''}
                                maxLength={25}
                                onChange={(event) =>
                                    updateButton(buttonIndex, {
                                        text: event.target.value,
                                    })
                                }
                                placeholder="Texto do botão"
                                className="min-w-0 flex-1 basis-36"
                            />
                            {type === 'url' && (
                                <Input
                                    aria-label={`Endereço do botão ${buttonIndex + 1} do card ${index + 1}`}
                                    value={button?.url ?? ''}
                                    onChange={(event) =>
                                        updateButton(buttonIndex, {
                                            url: event.target.value,
                                        })
                                    }
                                    placeholder="https://exemplo.com/produto"
                                    className="min-w-0 flex-1 basis-48"
                                />
                            )}
                            {type === 'phone_number' && (
                                <Input
                                    aria-label={`Telefone do botão ${buttonIndex + 1} do card ${index + 1}`}
                                    value={button?.phoneNumber ?? ''}
                                    onChange={(event) =>
                                        updateButton(buttonIndex, {
                                            phoneNumber: event.target.value,
                                        })
                                    }
                                    placeholder="+5511999999999"
                                    className="min-w-0 flex-1 basis-48"
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
