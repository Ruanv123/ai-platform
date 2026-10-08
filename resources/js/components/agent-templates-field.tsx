import { Link } from '@inertiajs/react';
import { AlertTriangle, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as createTemplate } from '@/routes/templates';

export type AgentTemplateOption = {
    id: number;
    name: string;
    language: string;
    body: string;
    categoryLabel: string;
    accountId: number;
    accountLabel: string;
    statusLabel: string;
    usable: boolean;
    variablesWithoutDescription: number;
};

export type AgentTemplateUsageOption = {
    value: string;
    label: string;
    automatic: boolean;
};

export type AgentTemplateLink = {
    templateId: number;
    usage: string;
};

export default function AgentTemplatesField({
    templates,
    usages,
    links,
    reminderLeadHours,
    followUpAfterHours,
    error,
    onLinksChange,
    onReminderLeadHoursChange,
    onFollowUpAfterHoursChange,
}: {
    templates: AgentTemplateOption[];
    usages: AgentTemplateUsageOption[];
    links: AgentTemplateLink[];
    reminderLeadHours: string;
    followUpAfterHours: string;
    error?: string;
    onLinksChange: (links: AgentTemplateLink[]) => void;
    onReminderLeadHoursChange: (hours: string) => void;
    onFollowUpAfterHoursChange: (hours: string) => void;
}) {
    const linkedIds = links.map((link) => link.templateId);
    const available = templates.filter(
        (template) => template.usable && !linkedIds.includes(template.id),
    );
    const hasReminder = links.some(
        (link) => link.usage === 'appointment_reminder',
    );
    const hasFollowUp = links.some((link) => link.usage === 'follow_up');
    const showsAccount =
        new Set(templates.map((template) => template.accountId)).size > 1;

    return (
        <section className="grid gap-3">
            <div className="grid gap-1">
                <Label>Templates do WhatsApp</Label>
                <p className="text-muted-foreground text-sm">
                    Templates aprovados que o Agente pode enviar, inclusive fora
                    da janela de 24 horas. A IA preenche as variáveis a partir
                    da descrição de cada uma.
                </p>
            </div>

            {templates.length === 0 ? (
                <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                    Nenhum Template aprovado ainda.{' '}
                    <Link
                        className="text-primary underline"
                        href={createTemplate.url()}
                    >
                        Crie um a partir dos modelos prontos
                    </Link>{' '}
                    e envie para a Meta.
                </p>
            ) : (
                <>
                    {links.map((link, index) => {
                        const template = templates.find(
                            (candidate) => candidate.id === link.templateId,
                        );

                        if (!template) {
                            return null;
                        }

                        return (
                            <div
                                key={link.templateId}
                                className="grid gap-2 rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="grid min-w-0 gap-0.5">
                                        <span className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                            {template.name}
                                            <Badge variant="outline">
                                                {template.categoryLabel}
                                            </Badge>
                                        </span>
                                        <span className="text-muted-foreground line-clamp-2 text-xs">
                                            {showsAccount &&
                                                `${template.accountLabel} · `}
                                            {template.body}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Remover ${template.name}`}
                                        onClick={() =>
                                            onLinksChange(
                                                links.filter(
                                                    (candidate) =>
                                                        candidate.templateId !==
                                                        link.templateId,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                                <select
                                    aria-label={`Para que o Agente usa ${template.name}`}
                                    value={link.usage}
                                    onChange={(event) =>
                                        onLinksChange(
                                            links.map((candidate, position) =>
                                                position === index
                                                    ? {
                                                          ...candidate,
                                                          usage: event.target
                                                              .value,
                                                      }
                                                    : candidate,
                                            ),
                                        )
                                    }
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                >
                                    {usages.map((usage) => (
                                        <option
                                            key={usage.value}
                                            value={usage.value}
                                        >
                                            {usage.label}
                                        </option>
                                    ))}
                                </select>
                                {!template.usable && (
                                    <p className="text-destructive flex items-center gap-1.5 text-xs">
                                        <AlertTriangle className="size-3.5" />
                                        {template.statusLabel}: o Agente ignora
                                        este Template até ele voltar a ser
                                        aprovado.
                                    </p>
                                )}
                                {template.usable &&
                                    template.variablesWithoutDescription >
                                        0 && (
                                        <p className="text-signal-attention flex items-center gap-1.5 text-xs">
                                            <AlertTriangle className="size-3.5" />
                                            {template.variablesWithoutDescription ===
                                            1
                                                ? '1 variável sem descrição'
                                                : `${template.variablesWithoutDescription} variáveis sem descrição`}
                                            : o preenchimento pela IA fica menos
                                            confiável.
                                        </p>
                                    )}
                            </div>
                        );
                    })}

                    {available.length > 0 && (
                        <select
                            aria-label="Adicionar Template ao Agente"
                            value=""
                            onChange={(event) => {
                                const templateId = Number(event.target.value);

                                if (templateId) {
                                    onLinksChange([
                                        ...links,
                                        { templateId, usage: 'on_demand' },
                                    ]);
                                }
                            }}
                            className="border-input bg-background text-muted-foreground h-9 rounded-md border px-3 text-sm"
                        >
                            <option value="">Adicionar Template…</option>
                            {available.map((template) => (
                                <option key={template.id} value={template.id}>
                                    {showsAccount
                                        ? `${template.name} · ${template.accountLabel}`
                                        : template.name}
                                </option>
                            ))}
                        </select>
                    )}

                    {(hasReminder || hasFollowUp) && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {hasReminder && (
                                <div className="grid gap-2">
                                    <Label htmlFor="reminder-lead-hours">
                                        Antecedência do lembrete (horas)
                                    </Label>
                                    <Input
                                        id="reminder-lead-hours"
                                        type="number"
                                        min={1}
                                        max={168}
                                        value={reminderLeadHours}
                                        onChange={(event) =>
                                            onReminderLeadHoursChange(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            )}
                            {hasFollowUp && (
                                <div className="grid gap-2">
                                    <Label htmlFor="follow-up-after-hours">
                                        Follow-up após quantas horas sem
                                        resposta
                                    </Label>
                                    <Input
                                        id="follow-up-after-hours"
                                        type="number"
                                        min={1}
                                        max={168}
                                        value={followUpAfterHours}
                                        onChange={(event) =>
                                            onFollowUpAfterHoursChange(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}
            <InputError message={error} />
        </section>
    );
}
