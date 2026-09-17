import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/business-hours';
import type { CompanyOption, Team } from '@/types';

type Props = {
    team: Pick<Team, 'businessHours' | 'timezone'>;
    timezones: string[];
    weekdays: CompanyOption[];
    canUpdate: boolean;
};

function isBusinessDay(enabled: boolean | string | undefined): boolean {
    return enabled === undefined || enabled === true || enabled === '1';
}

export default function BusinessHours({
    team,
    timezones,
    weekdays,
    canUpdate,
}: Props) {
    return (
        <>
            <Head title="Horário de atendimento" />

            <h1 className="sr-only">Horário de atendimento</h1>

            {canUpdate ? (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Horário de atendimento"
                        description="Defina os dias e horários em que sua Empresa atende."
                    />

                    <Form
                        {...update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-8"
                    >
                        {({ errors, processing, wasSuccessful }) => (
                            <>
                                <div className="grid gap-4">
                                    <div>
                                        <Label>Horário por dia</Label>
                                        <p className="text-muted-foreground text-sm">
                                            Marque os dias de atendimento e
                                            informe a faixa de cada um.
                                        </p>
                                    </div>

                                    <div className="space-y-3">
                                        {weekdays.map((weekday) => {
                                            const hours =
                                                team.businessHours?.[
                                                    weekday.value
                                                ];
                                            const enabled = isBusinessDay(
                                                hours?.enabled,
                                            );

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
                                                            defaultChecked={
                                                                enabled
                                                            }
                                                            className="accent-primary"
                                                        />
                                                        {weekday.label}
                                                    </label>
                                                    <div className="grid gap-1">
                                                        <Input
                                                            type="time"
                                                            name={`business_hours[${weekday.value}][open]`}
                                                            defaultValue={
                                                                hours?.open ??
                                                                '09:00'
                                                            }
                                                            aria-label={`Abertura ${weekday.label}`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `business_hours.${weekday.value}.open`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <span className="text-muted-foreground text-sm">
                                                        até
                                                    </span>
                                                    <div className="grid gap-1">
                                                        <Input
                                                            type="time"
                                                            name={`business_hours[${weekday.value}][close]`}
                                                            defaultValue={
                                                                hours?.close ??
                                                                '18:00'
                                                            }
                                                            aria-label={`Fechamento ${weekday.label}`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `business_hours.${weekday.value}.close`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <InputError
                                        message={errors.business_hours}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">
                                        Fuso horário
                                    </Label>
                                    <select
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={
                                            team.timezone ?? 'America/Sao_Paulo'
                                        }
                                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                        required
                                    >
                                        <option value="">
                                            Selecione um fuso horário
                                        </option>
                                        {timezones.map((timezone) => (
                                            <option
                                                key={timezone}
                                                value={timezone}
                                            >
                                                {timezone}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.timezone} />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        data-test="business-hours-save-button"
                                        disabled={processing}
                                    >
                                        Salvar alterações
                                    </Button>
                                    {wasSuccessful ? (
                                        <p className="text-sm text-green-600 dark:text-green-400">
                                            Horário de atendimento salvo.
                                        </p>
                                    ) : null}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            ) : (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Horário de atendimento"
                        description="Veja quando sua Empresa atende."
                    />

                    <div className="space-y-3 rounded-xl border p-5">
                        {weekdays.map((weekday) => {
                            const hours = team.businessHours?.[weekday.value];
                            const hasHours =
                                isBusinessDay(hours?.enabled) &&
                                hours?.open &&
                                hours?.close;

                            return (
                                <div
                                    key={weekday.value}
                                    className="flex items-center justify-between gap-4 text-sm"
                                >
                                    <span className="font-medium">
                                        {weekday.label}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {hasHours
                                            ? `${hours.open} até ${hours.close}`
                                            : 'Fechado'}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <p className="text-muted-foreground text-sm">
                        Fuso horário: <strong>{team.timezone}</strong>
                    </p>
                </div>
            )}
        </>
    );
}

BusinessHours.layout = {
    breadcrumbs: [
        {
            title: 'Horário de atendimento',
            href: edit(),
        },
    ],
};
