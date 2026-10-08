import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import CarouselCardEditor from './carousel-card-editor';
import { emptyCard } from './template-draft';
import type {
    CardButtonType,
    CardDraft,
    TemplateDraft,
} from './template-draft';

export default function CarouselCardsField({
    draft,
    errors,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    const [activeKey, setActiveKey] = useState(draft.cards[0]?.key ?? '');
    const cards = draft.cards;
    const activeIndex = Math.max(
        0,
        cards.findIndex((card) => card.key === activeKey),
    );
    const activeCard = cards[activeIndex];
    const buttonTypes = draft.cardButtonTypes;

    const updateCard = (index: number, changes: Partial<CardDraft>) =>
        onChange({
            cards: cards.map((card, position) =>
                position === index ? { ...card, ...changes } : card,
            ),
        });

    const addCard = () => {
        const card = emptyCard(buttonTypes.length);
        onChange({ cards: [...cards, card] });
        setActiveKey(card.key);
    };

    const removeCard = () => {
        const remaining = cards.filter((card, index) => index !== activeIndex);
        onChange({ cards: remaining });
        setActiveKey(remaining[Math.max(0, activeIndex - 1)]?.key ?? '');
    };

    const setButtonTypes = (types: CardButtonType[], removedIndex?: number) =>
        onChange({
            cardButtonTypes: types,
            cards: cards.map((card) => ({
                ...card,
                buttons:
                    removedIndex === undefined
                        ? types.map(
                              (type, index) =>
                                  card.buttons[index] ?? {
                                      text: '',
                                      url: '',
                                      phoneNumber: '',
                                      example: '',
                                  },
                          )
                        : card.buttons.filter(
                              (button, index) => index !== removedIndex,
                          ),
            })),
        });

    const cardErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('cards'))
        .map(([, message]) => message);

    return (
        <section className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Cards{' '}
                    <span className="text-muted-foreground font-normal">
                        ({cards.length} de 10)
                    </span>
                </h3>
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="cards_have_body"
                            checked={draft.cardsHaveBody}
                            onCheckedChange={(checked) =>
                                onChange({ cardsHaveBody: checked === true })
                            }
                        />
                        <Label htmlFor="cards_have_body">Texto nos cards</Label>
                    </div>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={draft.cardHeaderFormat}
                        aria-label="Mídia dos cards"
                        onValueChange={(value) => {
                            if (value && value !== draft.cardHeaderFormat) {
                                onChange({
                                    cardHeaderFormat: value as
                                        | 'IMAGE'
                                        | 'VIDEO',
                                    cards: cards.map((card) => ({
                                        ...card,
                                        media: null,
                                        existingIndex: null,
                                        existingMediaName: null,
                                    })),
                                });
                            }
                        }}
                    >
                        <ToggleGroupItem value="IMAGE" className="px-2.5">
                            Imagem
                        </ToggleGroupItem>
                        <ToggleGroupItem value="VIDEO" className="px-2.5">
                            Vídeo
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border">
                <div
                    role="tablist"
                    aria-label="Cards do carrossel"
                    className="bg-muted/50 flex items-center gap-1 overflow-x-auto border-b p-1.5"
                >
                    {cards.map((card, index) => (
                        <button
                            key={card.key}
                            type="button"
                            role="tab"
                            aria-selected={index === activeIndex}
                            onClick={() => setActiveKey(card.key)}
                            className={cn(
                                'focus-visible:ring-ring/50 inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-[13px] outline-none focus-visible:ring-[3px]',
                                index === activeIndex
                                    ? 'bg-background font-semibold shadow-xs'
                                    : 'text-muted-foreground border-transparent font-medium',
                            )}
                        >
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    card.media || card.existingMediaName
                                        ? 'bg-green-600'
                                        : 'bg-border',
                                )}
                            />
                            Card {index + 1}
                        </button>
                    ))}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="shrink-0 border border-dashed"
                        disabled={cards.length >= 10}
                        onClick={addCard}
                    >
                        <Plus /> Card
                    </Button>
                </div>
                {activeCard && (
                    <div role="tabpanel" className="grid gap-3.5 p-3.5">
                        <CarouselCardEditor
                            key={activeCard.key}
                            card={activeCard}
                            index={activeIndex}
                            mediaKind={draft.cardHeaderFormat}
                            withBody={draft.cardsHaveBody}
                            buttonTypes={buttonTypes}
                            errors={errors}
                            onChange={(changes) =>
                                updateCard(activeIndex, changes)
                            }
                        />
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                            <span className="text-muted-foreground text-xs">
                                Todos os cards usam o mesmo tipo de mídia e os
                                mesmos botões, cada um com o seu conteúdo.
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                disabled={cards.length <= 2}
                                onClick={removeCard}
                            >
                                Remover card
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <div className="grid gap-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-sm font-medium">
                        Botões dos cards{' '}
                        <span className="text-muted-foreground font-normal">
                            ({buttonTypes.length} de 2, iguais em todos os
                            cards)
                        </span>
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={buttonTypes.length >= 2}
                        onClick={() =>
                            setButtonTypes([...buttonTypes, 'quick_reply'])
                        }
                    >
                        <Plus /> Adicionar botão
                    </Button>
                </div>
                {buttonTypes.map((type, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <Label
                            htmlFor={`card-button-type-${index}`}
                            className="text-muted-foreground w-16 font-normal"
                        >
                            Botão {index + 1}
                        </Label>
                        <select
                            id={`card-button-type-${index}`}
                            value={type}
                            onChange={(event) =>
                                setButtonTypes(
                                    buttonTypes.map((current, position) =>
                                        position === index
                                            ? (event.target
                                                  .value as CardButtonType)
                                            : current,
                                    ),
                                )
                            }
                            className="border-input bg-background h-9 flex-1 rounded-md border px-3 text-sm"
                        >
                            <option value="quick_reply">Resposta rápida</option>
                            <option value="url">URL</option>
                            <option value="phone_number">Telefone</option>
                        </select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Remover botão ${index + 1} dos cards`}
                            onClick={() =>
                                setButtonTypes(
                                    buttonTypes.filter(
                                        (current, position) =>
                                            position !== index,
                                    ),
                                    index,
                                )
                            }
                        >
                            <X />
                        </Button>
                    </div>
                ))}
            </div>
            {cardErrors.length > 0 && <InputError message={cardErrors[0]} />}
        </section>
    );
}
