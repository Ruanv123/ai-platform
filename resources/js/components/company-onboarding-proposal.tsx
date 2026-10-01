import { Form, Head } from '@inertiajs/react';
import OnboardingProposalField from '@/components/onboarding-proposal-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { proposal as confirmProposal } from '@/routes/onboarding/company';
import type { CompanyOption } from '@/types';

type ProposalField = {
    value: unknown;
    sourceUrl: string;
};

type Props = {
    name: string;
    fields: Record<string, ProposalField>;
    segments: CompanyOption[];
    weekdays: CompanyOption[];
};

type Schedule = {
    enabled?: boolean | string;
    open?: string | null;
    close?: string | null;
};

function valueAsString(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function valueAsRecord(value: unknown): Record<string, Schedule> {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, Schedule>)
        : {};
}

function scheduleIsEnabled(value: Schedule | undefined): boolean {
    return value?.enabled === true || value?.enabled === '1';
}

export default function CompanyOnboardingProposal({
    name,
    fields,
    segments,
    weekdays,
}: Props) {
    const businessHours = valueAsRecord(fields.business_hours?.value);
    const examples = Array.isArray(fields.brand_voice_examples?.value)
        ? fields.brand_voice_examples.value.filter(
              (example): example is string => typeof example === 'string',
          )
        : [];

    return (
        <>
            <Head title="Confira os dados da Empresa" />

            <main className="mx-auto grid max-w-3xl gap-8 px-4 py-8 sm:px-6 lg:py-12">
                <div className="grid gap-3">
                    <p className="text-primary text-sm font-medium">
                        3 de 4 · Conferência
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        Confira os dados da {name}
                    </h1>
                    <p className="text-muted-foreground text-pretty">
                        Revise e edite o que encontramos. Cada informação tem a
                        página de origem para você conferir.
                    </p>
                </div>

                <Form {...confirmProposal.form()} className="grid gap-6">
                    {({ errors, processing }) => (
                        <>
                            <section className="bg-card grid gap-6 rounded-xl border p-5 sm:p-7">
                                <div className="grid gap-2">
                                    <h2 className="font-medium">
                                        Dados da Empresa
                                    </h2>
                                    <p className="text-muted-foreground text-sm text-pretty">
                                        Os campos sem informação encontrada
                                        ficam vazios. Preencha apenas o que
                                        quiser confirmar.
                                    </p>
                                </div>

                                <OnboardingProposalField
                                    id="segment"
                                    label="Segmento"
                                    sourceUrl={fields.segment?.sourceUrl}
                                    error={errors.segment}
                                >
                                    <select
                                        id="segment"
                                        name="segment"
                                        defaultValue={valueAsString(
                                            fields.segment?.value,
                                        )}
                                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    >
                                        <option value="">
                                            Selecione um segmento
                                        </option>
                                        {segments.map((segment) => (
                                            <option
                                                key={segment.value}
                                                value={segment.value}
                                            >
                                                {segment.label}
                                            </option>
                                        ))}
                                    </select>
                                </OnboardingProposalField>

                                <OnboardingProposalField
                                    id="business_description"
                                    label="Descrição do negócio"
                                    sourceUrl={
                                        fields.business_description?.sourceUrl
                                    }
                                    error={errors.business_description}
                                >
                                    <textarea
                                        id="business_description"
                                        name="business_description"
                                        defaultValue={valueAsString(
                                            fields.business_description?.value,
                                        )}
                                        placeholder="O que sua empresa faz?"
                                        className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    />
                                </OnboardingProposalField>

                                <OnboardingProposalField
                                    id="products_services"
                                    label="Produtos e serviços"
                                    sourceUrl={
                                        fields.products_services?.sourceUrl
                                    }
                                    error={errors.products_services}
                                >
                                    <textarea
                                        id="products_services"
                                        name="products_services"
                                        defaultValue={valueAsString(
                                            fields.products_services?.value,
                                        )}
                                        placeholder="Quais produtos ou serviços você oferece?"
                                        className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    />
                                </OnboardingProposalField>

                                <OnboardingProposalField
                                    id="target_audience"
                                    label="Público-alvo"
                                    sourceUrl={
                                        fields.target_audience?.sourceUrl
                                    }
                                    error={errors.target_audience}
                                >
                                    <textarea
                                        id="target_audience"
                                        name="target_audience"
                                        defaultValue={valueAsString(
                                            fields.target_audience?.value,
                                        )}
                                        placeholder="Para quem sua empresa trabalha?"
                                        className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    />
                                </OnboardingProposalField>

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <OnboardingProposalField
                                        id="phone"
                                        label="Telefone público"
                                        sourceUrl={fields.phone?.sourceUrl}
                                        error={errors.phone}
                                    >
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            defaultValue={valueAsString(
                                                fields.phone?.value,
                                            )}
                                            placeholder="(11) 99999-9999"
                                        />
                                    </OnboardingProposalField>
                                    <OnboardingProposalField
                                        id="public_email"
                                        label="E-mail público"
                                        sourceUrl={
                                            fields.public_email?.sourceUrl
                                        }
                                        error={errors.public_email}
                                    >
                                        <Input
                                            id="public_email"
                                            name="public_email"
                                            type="email"
                                            defaultValue={valueAsString(
                                                fields.public_email?.value,
                                            )}
                                            placeholder="contato@empresa.com.br"
                                        />
                                    </OnboardingProposalField>
                                </div>

                                <OnboardingProposalField
                                    id="address"
                                    label="Endereço"
                                    sourceUrl={fields.address?.sourceUrl}
                                    error={errors.address}
                                >
                                    <textarea
                                        id="address"
                                        name="address"
                                        defaultValue={valueAsString(
                                            fields.address?.value,
                                        )}
                                        placeholder="Cidade, estado ou endereço completo"
                                        className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm"
                                    />
                                </OnboardingProposalField>

                                <OnboardingProposalField
                                    id="business_hours"
                                    label="Horário de atendimento"
                                    sourceUrl={fields.business_hours?.sourceUrl}
                                    error={errors.business_hours}
                                >
                                    <div className="grid gap-3">
                                        {weekdays.map((weekday) => {
                                            const schedule =
                                                businessHours[weekday.value];

                                            return (
                                                <div
                                                    key={weekday.value}
                                                    className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center"
                                                >
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <input
                                                            type="hidden"
                                                            name={`business_hours[${weekday.value}][enabled]`}
                                                            value="0"
                                                        />
                                                        <input
                                                            type="checkbox"
                                                            name={`business_hours[${weekday.value}][enabled]`}
                                                            value="1"
                                                            defaultChecked={scheduleIsEnabled(
                                                                schedule,
                                                            )}
                                                            className="accent-primary"
                                                        />
                                                        {weekday.label}
                                                    </label>
                                                    <Input
                                                        type="time"
                                                        name={`business_hours[${weekday.value}][open]`}
                                                        defaultValue={
                                                            schedule?.open ?? ''
                                                        }
                                                        aria-label={`Abertura ${weekday.label}`}
                                                    />
                                                    <span className="text-muted-foreground text-sm">
                                                        até
                                                    </span>
                                                    <Input
                                                        type="time"
                                                        name={`business_hours[${weekday.value}][close]`}
                                                        defaultValue={
                                                            schedule?.close ??
                                                            ''
                                                        }
                                                        aria-label={`Fechamento ${weekday.label}`}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </OnboardingProposalField>
                            </section>

                            <section className="bg-card grid gap-6 rounded-xl border p-5 sm:p-7">
                                <div className="grid gap-2">
                                    <h2 className="font-medium">
                                        Voz da marca
                                    </h2>
                                    <p className="text-muted-foreground text-sm text-pretty">
                                        Ajuste como os Agentes devem falar e as
                                        frases que mostram esse jeito.
                                    </p>
                                </div>

                                <OnboardingProposalField
                                    id="brand_voice"
                                    label="Como a marca soa"
                                    sourceUrl={fields.brand_voice?.sourceUrl}
                                    error={errors.brand_voice}
                                >
                                    <textarea
                                        id="brand_voice"
                                        name="brand_voice"
                                        defaultValue={valueAsString(
                                            fields.brand_voice?.value,
                                        )}
                                        maxLength={1000}
                                        placeholder="Descreva o jeito da marca se comunicar"
                                        className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    />
                                </OnboardingProposalField>

                                <OnboardingProposalField
                                    id="brand_voice_example_0"
                                    label="Frases de exemplo"
                                    sourceUrl={
                                        fields.brand_voice_examples?.sourceUrl
                                    }
                                    error={errors.brand_voice_examples}
                                >
                                    <div className="grid gap-3">
                                        {Array.from(
                                            { length: 4 },
                                            (_, index) => (
                                                <Input
                                                    key={index}
                                                    id={`brand_voice_example_${index}`}
                                                    name="brand_voice_examples[]"
                                                    defaultValue={
                                                        examples[index] ?? ''
                                                    }
                                                    maxLength={1000}
                                                    placeholder={`Frase ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                </OnboardingProposalField>
                            </section>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                                className="w-full sm:w-auto sm:justify-self-start"
                            >
                                {processing ? <Spinner /> : null}
                                Confirmar dados e ir ao painel
                            </Button>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}
