import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/onboarding/company';
import type { CompanyOption } from '@/types';

type Props = {
    segments: CompanyOption[];
    timezones: string[];
    weekdays: CompanyOption[];
};

export default function CompanyOnboarding({ segments, timezones, weekdays }: Props) {
    return (
        <>
            <Head title="Configure sua Empresa" />

            <div className="mx-auto max-w-3xl space-y-8 px-4 py-8 sm:px-6 lg:py-12">
                <div className="space-y-2">
                    <p className="text-primary text-sm font-medium">Primeiro passo</p>
                    <h1 className="text-3xl font-semibold tracking-tight">Configure sua Empresa</h1>
                    <p className="text-muted-foreground">Conte um pouco sobre o seu negócio para preparar sua plataforma de atendimento.</p>
                </div>

                <Form {...store.form()} className="space-y-8">
                    {({ errors, processing }) => (
                        <>
                            <section className="space-y-6 rounded-xl border p-5 sm:p-6">
                                <div>
                                    <h2 className="font-medium">Sobre o negócio</h2>
                                    <p className="text-muted-foreground text-sm">Essas informações ajudam seus agentes a entenderem sua empresa.</p>
                                </div>

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <label htmlFor="name" className="text-sm font-medium">Nome fantasia</label>
                                        <Input id="name" name="name" required autoFocus placeholder="Como sua empresa é conhecida?" />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <label htmlFor="segment" className="text-sm font-medium">Segmento</label>
                                        <select id="segment" name="segment" required defaultValue="" className="border-input bg-background h-9 rounded-md border px-3 text-sm">
                                            <option value="">Selecione um segmento</option>
                                            {segments.map((segment) => <option key={segment.value} value={segment.value}>{segment.label}</option>)}
                                        </select>
                                        <InputError message={errors.segment} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="website" className="text-sm font-medium">Site <span className="text-muted-foreground font-normal">(opcional)</span></label>
                                    <Input id="website" name="website" type="url" placeholder="https://exemplo.com.br" />
                                    <InputError message={errors.website} />
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="business_description" className="text-sm font-medium">Descrição do negócio</label>
                                    <textarea id="business_description" name="business_description" required placeholder="O que sua empresa faz?" className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm" />
                                    <InputError message={errors.business_description} />
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="products_services" className="text-sm font-medium">Produtos e serviços</label>
                                    <textarea id="products_services" name="products_services" required placeholder="Quais produtos ou serviços você oferece?" className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm" />
                                    <InputError message={errors.products_services} />
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="target_audience" className="text-sm font-medium">Público-alvo</label>
                                    <textarea id="target_audience" name="target_audience" required placeholder="Para quem sua empresa trabalha?" className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm" />
                                    <InputError message={errors.target_audience} />
                                </div>
                            </section>

                            <section className="space-y-6 rounded-xl border p-5 sm:p-6">
                                <div>
                                    <h2 className="font-medium">Atendimento e contato</h2>
                                    <p className="text-muted-foreground text-sm">Você poderá atualizar essas informações depois.</p>
                                </div>

                                <div className="grid gap-3">
                                    {weekdays.map((weekday) => (
                                        <div key={weekday.value} className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center">
                                            <label className="flex items-center gap-2 text-sm">
                                                <input type="hidden" name={`business_hours[${weekday.value}][enabled]`} value="0" />
                                                <input type="checkbox" name={`business_hours[${weekday.value}][enabled]`} value="1" defaultChecked className="accent-primary" />
                                                {weekday.label}
                                            </label>
                                            <Input type="time" name={`business_hours[${weekday.value}][open]`} defaultValue="09:00" aria-label={`Abertura ${weekday.label}`} />
                                            <span className="text-muted-foreground text-sm">até</span>
                                            <Input type="time" name={`business_hours[${weekday.value}][close]`} defaultValue="18:00" aria-label={`Fechamento ${weekday.label}`} />
                                        </div>
                                    ))}
                                    <InputError message={errors.business_hours} />
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="timezone" className="text-sm font-medium">Fuso horário</label>
                                    <select id="timezone" name="timezone" required defaultValue="America/Sao_Paulo" className="border-input bg-background h-9 rounded-md border px-3 text-sm">
                                        {timezones.map((timezone) => <option key={timezone} value={timezone}>{timezone}</option>)}
                                    </select>
                                    <InputError message={errors.timezone} />
                                </div>

                                <div className="grid gap-6 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <label htmlFor="phone" className="text-sm font-medium">Telefone público <span className="text-muted-foreground font-normal">(opcional)</span></label>
                                        <Input id="phone" name="phone" type="tel" placeholder="(11) 99999-9999" />
                                        <InputError message={errors.phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <label htmlFor="public_email" className="text-sm font-medium">E-mail público <span className="text-muted-foreground font-normal">(opcional)</span></label>
                                        <Input id="public_email" name="public_email" type="email" placeholder="contato@empresa.com.br" />
                                        <InputError message={errors.public_email} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <label htmlFor="address" className="text-sm font-medium">Endereço <span className="text-muted-foreground font-normal">(opcional)</span></label>
                                    <textarea id="address" name="address" className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm" />
                                    <InputError message={errors.address} />
                                </div>
                            </section>

                            <Button type="submit" size="lg" disabled={processing} className="w-full sm:w-auto">
                                {processing && <Spinner />}
                                Concluir configuração
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
