import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TemplateDraft } from './template-draft';

export default function LimitedTimeOfferFields({
    draft,
    errors,
    section,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    section: 'offer' | 'buttons';
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    if (section === 'offer') {
        return (
            <section className="grid gap-3">
                <h3 className="text-sm font-semibold">Oferta</h3>
                <div className="grid items-end gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="offer_text">Título da oferta</Label>
                        <Input
                            id="offer_text"
                            value={draft.offerText}
                            maxLength={16}
                            onChange={(event) =>
                                onChange({ offerText: event.target.value })
                            }
                            placeholder="Ex.: Só hoje!"
                        />
                    </div>
                    <div className="flex h-9 items-center gap-2">
                        <Checkbox
                            id="offer_has_expiration"
                            checked={draft.offerHasExpiration}
                            onCheckedChange={(checked) =>
                                onChange({
                                    offerHasExpiration: checked === true,
                                })
                            }
                        />
                        <Label htmlFor="offer_has_expiration">
                            Mostrar contagem regressiva
                        </Label>
                    </div>
                </div>
                <p className="text-muted-foreground text-xs">
                    {draft.offerText.length}/16 caracteres · A data de término é
                    definida no envio de cada mensagem.
                </p>
                <InputError message={errors.offer_text} />
            </section>
        );
    }

    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-semibold">Botões</h3>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid content-start gap-2">
                    <Label htmlFor="offer_code">Código da oferta</Label>
                    <Input
                        id="offer_code"
                        value={draft.offerCode}
                        maxLength={15}
                        onChange={(event) =>
                            onChange({
                                offerCode: event.target.value
                                    .toUpperCase()
                                    .replace(/\s/g, ''),
                            })
                        }
                        placeholder="Ex.: RELAMPAGO"
                    />
                </div>
                <div className="grid content-start gap-2">
                    <Label htmlFor="offer_url_text">Texto do link</Label>
                    <Input
                        id="offer_url_text"
                        value={draft.offerUrlText}
                        maxLength={25}
                        onChange={(event) =>
                            onChange({ offerUrlText: event.target.value })
                        }
                        placeholder="Ex.: Ver oferta"
                    />
                </div>
                <div className="grid content-start gap-2">
                    <Label htmlFor="offer_url">Endereço</Label>
                    <Input
                        id="offer_url"
                        value={draft.offerUrl}
                        onChange={(event) =>
                            onChange({ offerUrl: event.target.value })
                        }
                        placeholder="https://exemplo.com/oferta"
                    />
                </div>
            </div>
            {draft.offerUrl.includes('{{') && (
                <Input
                    aria-label="Exemplo do endereço da oferta"
                    value={draft.offerUrlExample}
                    onChange={(event) =>
                        onChange({ offerUrlExample: event.target.value })
                    }
                    placeholder="Endereço completo de exemplo usado pela Meta"
                />
            )}
            <InputError message={errors.buttons} />
        </section>
    );
}
