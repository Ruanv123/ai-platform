import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TemplateDraft } from './template-draft';

export default function CouponFields({
    draft,
    errors,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-semibold">Botões</h3>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor="coupon_code">Código do cupom</Label>
                    <Input
                        id="coupon_code"
                        value={draft.couponCode}
                        maxLength={20}
                        onChange={(event) =>
                            onChange({
                                couponCode: event.target.value
                                    .toUpperCase()
                                    .replace(/\s/g, ''),
                            })
                        }
                        placeholder="Ex.: BEMVINDO10"
                    />
                    <p className="text-muted-foreground text-xs">
                        {draft.couponCode.length}/20 caracteres · vira o botão
                        “Copiar código”
                    </p>
                    <InputError message={errors['buttons.0.example']} />
                </div>
                <div className="grid content-start gap-2">
                    <Label htmlFor="coupon_reply">
                        Resposta rápida{' '}
                        <span className="text-muted-foreground font-normal">
                            (opcional)
                        </span>
                    </Label>
                    <Input
                        id="coupon_reply"
                        value={draft.couponQuickReply}
                        maxLength={25}
                        onChange={(event) =>
                            onChange({ couponQuickReply: event.target.value })
                        }
                        placeholder="Ex.: Não tenho interesse"
                    />
                </div>
            </div>
            <InputError message={errors.buttons} />
        </section>
    );
}
