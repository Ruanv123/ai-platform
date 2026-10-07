import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { accept } from '@/routes/company/site-read-proposals/fields';
import { acceptAll, discard } from '@/routes/company/site-read-proposals';

type ProposalField = {
    label: string;
    current: unknown;
    proposed: unknown;
    sourceUrl: string;
};

type Proposal = {
    id: number;
    fields: Record<string, ProposalField>;
};

type Props = {
    proposal: Proposal | null;
    canUpdate: boolean;
};

const weekdayLabels: Record<string, string> = {
    monday: 'Segunda-feira',
    tuesday: 'Terça-feira',
    wednesday: 'Quarta-feira',
    thursday: 'Quinta-feira',
    friday: 'Sexta-feira',
    saturday: 'Sábado',
    sunday: 'Domingo',
};

function formatValue(fieldName: string, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return 'Não informado';
    }

    if (
        fieldName === 'business_hours' &&
        typeof value === 'object' &&
        !Array.isArray(value)
    ) {
        return Object.entries(value)
            .map(([day, hours]) => {
                if (
                    hours === null ||
                    typeof hours !== 'object' ||
                    Array.isArray(hours)
                ) {
                    return `${weekdayLabels[day] ?? day}: Não informado`;
                }

                const schedule = hours as {
                    enabled?: boolean;
                    open?: string | null;
                    close?: string | null;
                };

                return schedule.enabled
                    ? `${weekdayLabels[day] ?? day}: ${schedule.open ?? 'Não informado'} às ${schedule.close ?? 'Não informado'}`
                    : `${weekdayLabels[day] ?? day}: Fechado`;
            })
            .join('\n');
    }

    if (Array.isArray(value)) {
        return value
            .map((item) =>
                typeof item === 'string' ? `“${item}”` : String(item),
            )
            .join('\n');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
        ? String(value)
        : 'Não informado';
}

export default function SiteReadProposalReview({ proposal, canUpdate }: Props) {
    if (proposal === null) {
        return null;
    }

    const fields = Object.entries(proposal.fields);

    return (
        <section className="bg-card space-y-5 rounded-xl border p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="grid gap-1">
                    <h2 className="text-base font-semibold">
                        Proposta da Leitura do site
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Confira o valor atual e a sugestão antes de atualizar os
                        dados da Empresa.
                    </p>
                </div>
                {canUpdate && fields.length > 0 ? (
                    <Form action={acceptAll.url(proposal.id)} method="post">
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                Aceitar tudo
                            </Button>
                        )}
                    </Form>
                ) : null}
            </div>

            {fields.length === 0 ? (
                <p className="bg-muted/50 text-muted-foreground rounded-lg p-4 text-sm">
                    A Leitura não encontrou diferenças nos dados atuais da
                    Empresa.
                </p>
            ) : (
                <div className="grid gap-4">
                    {fields.map(([fieldName, field]) => (
                        <article
                            key={fieldName}
                            className="grid gap-4 rounded-lg border p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="grid gap-1">
                                    <h3 className="text-sm font-semibold">
                                        {field.label}
                                    </h3>
                                    <a
                                        href={field.sourceUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary text-sm underline underline-offset-4"
                                    >
                                        Ver página de origem
                                    </a>
                                </div>
                                {canUpdate ? (
                                    <Form
                                        action={accept.url({
                                            proposal: proposal.id,
                                            field: fieldName,
                                        })}
                                        method="post"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Aceitar campo
                                            </Button>
                                        )}
                                    </Form>
                                ) : null}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="bg-muted/50 grid content-start gap-2 rounded-md p-3">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                        Atual
                                    </p>
                                    <p className="text-sm break-words whitespace-pre-wrap">
                                        {formatValue(fieldName, field.current)}
                                    </p>
                                </div>
                                <div className="border-primary/20 bg-primary/5 grid content-start gap-2 rounded-md border p-3">
                                    <p className="text-primary text-xs font-medium tracking-wide uppercase">
                                        Proposto
                                    </p>
                                    <p className="text-sm break-words whitespace-pre-wrap">
                                        {formatValue(fieldName, field.proposed)}
                                    </p>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            {canUpdate ? (
                <div className="flex justify-end border-t pt-4">
                    <Form action={discard.url(proposal.id)} method="post">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="ghost"
                                disabled={processing}
                            >
                                Descartar proposta
                            </Button>
                        )}
                    </Form>
                </div>
            ) : (
                <p className="text-muted-foreground border-t pt-4 text-sm">
                    Apenas Proprietário ou Administrador pode aceitar ou
                    descartar esta proposta.
                </p>
            )}
        </section>
    );
}
