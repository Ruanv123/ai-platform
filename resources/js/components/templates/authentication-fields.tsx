import { Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ChoiceCard from './choice-card';
import type { OtpType, TemplateDraft } from './template-draft';

const otpOptions: { value: OtpType; label: string; description: string }[] = [
    {
        value: 'copy_code',
        label: 'Copiar código',
        description:
            'O Contato toca para copiar e cola no app. Funciona em qualquer aparelho.',
    },
    {
        value: 'one_tap',
        label: 'Preenchimento automático',
        description:
            'Abre o seu app Android com o código preenchido. Em outros aparelhos vira “Copiar código”.',
    },
    {
        value: 'zero_tap',
        label: 'Sem toque',
        description:
            'O código chega direto no seu app Android, sem o Contato tocar em nada.',
    },
];

export default function AuthenticationFields({
    draft,
    errors,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    return (
        <div className="grid gap-6">
            <div className="bg-muted/50 flex items-start gap-2.5 rounded-lg border p-3">
                <Lock className="mt-0.5 size-4 shrink-0" />
                <div className="grid gap-0.5">
                    <span className="text-sm font-medium">
                        O texto é definido pela Meta
                    </span>
                    <span className="text-muted-foreground text-[13px]">
                        Templates de autenticação não aceitam texto livre,
                        links, mídia ou emojis. Você escolhe o botão e os
                        avisos.
                    </span>
                </div>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm leading-none font-semibold">
                    Como o Contato usa o código
                </legend>
                <div className="grid gap-3 sm:grid-cols-3">
                    {otpOptions.map((option) => (
                        <ChoiceCard
                            key={option.value}
                            name="otp_type"
                            value={option.value}
                            checked={draft.otpType === option.value}
                            label={option.label}
                            description={option.description}
                            onSelect={() => onChange({ otpType: option.value })}
                        />
                    ))}
                </div>
                <InputError message={errors.otp_type} />
            </fieldset>

            {draft.otpType !== 'copy_code' && (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="package_name">
                            Pacote do app Android
                        </Label>
                        <Input
                            id="package_name"
                            value={draft.packageName}
                            onChange={(event) =>
                                onChange({ packageName: event.target.value })
                            }
                            placeholder="com.empresa.app"
                        />
                        <InputError message={errors.package_name} />
                    </div>
                    <div className="grid content-start gap-2">
                        <Label htmlFor="signature_hash">
                            Hash da assinatura do app
                        </Label>
                        <Input
                            id="signature_hash"
                            value={draft.signatureHash}
                            maxLength={11}
                            onChange={(event) =>
                                onChange({ signatureHash: event.target.value })
                            }
                            placeholder="11 caracteres"
                        />
                        <InputError message={errors.signature_hash} />
                    </div>
                </div>
            )}

            <div className="grid gap-4">
                <div className="flex items-start gap-2.5">
                    <Checkbox
                        id="add_security_recommendation"
                        checked={draft.addSecurityRecommendation}
                        onCheckedChange={(checked) =>
                            onChange({
                                addSecurityRecommendation: checked === true,
                            })
                        }
                        className="mt-0.5"
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="add_security_recommendation">
                            Incluir aviso de segurança
                        </Label>
                        <span className="text-muted-foreground text-[13px]">
                            Acrescenta “Para sua segurança, não compartilhe este
                            código.”
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap items-start gap-2.5">
                    <Checkbox
                        id="expiration_enabled"
                        checked={draft.expirationEnabled}
                        onCheckedChange={(checked) =>
                            onChange({ expirationEnabled: checked === true })
                        }
                        className="mt-0.5"
                    />
                    <div className="grid flex-1 basis-60 gap-1">
                        <Label htmlFor="expiration_enabled">
                            Mostrar validade do código
                        </Label>
                        <span className="text-muted-foreground text-[13px]">
                            Aparece no rodapé da mensagem. De 1 a 90 minutos.
                        </span>
                    </div>
                    {draft.expirationEnabled && (
                        <span className="flex items-center gap-2">
                            <Input
                                type="number"
                                min={1}
                                max={90}
                                aria-label="Minutos de validade"
                                value={draft.codeExpirationMinutes}
                                onChange={(event) =>
                                    onChange({
                                        codeExpirationMinutes:
                                            event.target.value,
                                    })
                                }
                                className="w-20"
                            />
                            <span className="text-muted-foreground text-sm">
                                minutos
                            </span>
                        </span>
                    )}
                </div>
                <InputError message={errors.code_expiration_minutes} />
            </div>
        </div>
    );
}
