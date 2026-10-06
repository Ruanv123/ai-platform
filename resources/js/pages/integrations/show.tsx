import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import agenda from '@/routes/integrations/agenda';
import calendars from '@/routes/integrations/agenda/calendars';
import { destroy, index } from '@/routes/integrations';
import type { TeamPermissions } from '@/types';

type ConfiguredIntegration = {
    id: string;
    name: string;
    type: string;
    provider: string | null;
    accountEmail?: string | null;
    status: string;
    statusLabel: string;
    statusMessage: string | null;
};

type GoogleCalendar = {
    id: string;
    summary?: string;
    description?: string;
    primary?: boolean;
};

type SchedulableCalendar = {
    id: string;
    googleCalendarId: string;
    displayName: string;
    displayDescription: string | null;
    durationMinutes: number;
    minimumNoticeMinutes: number;
    businessHours: Record<string, { enabled?: boolean; open?: string | null; close?: string | null }> | null;
};

type Props = {
    integration: ConfiguredIntegration | null;
    googleCalendars: GoogleCalendar[];
    schedulableCalendars: SchedulableCalendar[];
    agendaError: string | null;
    permissions: TeamPermissions;
};

function PageHeader({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar para integrações"><Link href={index()}><ArrowLeft /></Link></Button>
            <Heading title={title} description={description} />
        </div>
    );
}

export default function IntegrationShowPage({ integration, googleCalendars, schedulableCalendars, agendaError, permissions }: Props) {
    const isConnected = integration?.status === 'connected';

    return (
        <>
            <Head title="Google Agenda" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader title="Google Agenda" description="Conecte a agenda real da Empresa para que o Agente encontre horários e marque Compromissos." />
                {agendaError && <div className="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border p-4 text-sm">{agendaError}</div>}
                {!integration ? (
                    <Card className="max-w-2xl">
                        <CardHeader><CardTitle>Conecte a Agenda</CardTitle><CardDescription>Você será levado ao Google para autorizar o acesso. A conexão fica com a Empresa, não com a pessoa que autorizou.</CardDescription></CardHeader>
                        <CardContent>{permissions.canManageIntegrations ? <Button asChild><a href={agenda.connect().url}>Conectar com Google</a></Button> : <p className="text-muted-foreground text-sm">Você não tem permissão para gerenciar Integrações.</p>}</CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="max-w-2xl">
                            <CardHeader className="flex-row items-start justify-between gap-4"><div><CardTitle className="flex items-center gap-2">Agenda conectada <StatusBadge status={integration.status} label={integration.statusLabel} /></CardTitle><CardDescription className="mt-2">{integration.accountEmail ? `Conectada como ${integration.accountEmail}` : 'Conta Google conectada'}</CardDescription>{integration.statusMessage && <p className="text-destructive mt-2 text-sm">{integration.statusMessage}</p>}</div></CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {permissions.canManageIntegrations && <Button asChild><a href={agenda.connect().url}>Reconectar</a></Button>}
                                {permissions.canManageIntegrations && <Button variant="outline" onClick={() => router.delete(destroy(integration.id).url)}><Trash2 /> Desconectar</Button>}
                            </CardContent>
                        </Card>
                        {isConnected && <CalendarSettings googleCalendars={googleCalendars} schedulableCalendars={schedulableCalendars} canManage={permissions.canManageIntegrations} />}
                    </>
                )}
            </div>
        </>
    );
}

function CalendarSettings({ googleCalendars, schedulableCalendars, canManage }: { googleCalendars: GoogleCalendar[]; schedulableCalendars: SchedulableCalendar[]; canManage: boolean }) {
    const enabledIds = new Set(schedulableCalendars.map((calendar) => calendar.googleCalendarId));

    return (
        <section className="grid gap-4">
            <div><h2 className="text-lg font-semibold">Calendários agendáveis</h2><p className="text-muted-foreground text-sm">Libere os Calendários em que o Agente poderá marcar e configure as regras de cada um.</p></div>
            {googleCalendars.length === 0 ? <Card className="border-dashed"><CardContent className="text-muted-foreground p-6 text-sm">Nenhum Calendário foi encontrado nesta conta Google.</CardContent></Card> : <div className="grid gap-4">{googleCalendars.map((calendar) => <CalendarCard key={calendar.id} calendar={calendar} configured={schedulableCalendars.find((item) => item.googleCalendarId === calendar.id)} enabled={enabledIds.has(calendar.id)} canManage={canManage} />)}</div>}
        </section>
    );
}

function CalendarCard({ calendar, configured, enabled, canManage }: { calendar: GoogleCalendar; configured?: SchedulableCalendar; enabled: boolean; canManage: boolean }) {
    const [name, setName] = useState(configured?.displayName ?? calendar.summary ?? calendar.id);
    const [description, setDescription] = useState(configured?.displayDescription ?? calendar.description ?? '');
    const [duration, setDuration] = useState(String(configured?.durationMinutes ?? 30));
    const [notice, setNotice] = useState(String(configured?.minimumNoticeMinutes ?? 120));
    const [hours, setHours] = useState(configured?.businessHours ? JSON.stringify(configured.businessHours, null, 2) : '');

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!configured) return;
        let businessHours: Record<string, Record<string, string | boolean | null>> | null = null;
        if (hours.trim() !== '') {
            try { businessHours = JSON.parse(hours) as Record<string, Record<string, string | boolean | null>>; } catch { return; }
        }
        router.patch(calendars.update(configured.id).url, { display_name: name, display_description: description, duration_minutes: Number(duration), minimum_notice_minutes: Number(notice), business_hours: businessHours }, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4"><div><CardTitle>{calendar.summary ?? calendar.id}</CardTitle><CardDescription>{calendar.description ?? 'Sem descrição no Google'}{calendar.primary && ' · Principal'}</CardDescription></div><Badge variant={enabled ? 'default' : 'secondary'}>{enabled ? 'Agendável' : 'Não liberado'}</Badge></CardHeader>
            <CardContent className="grid gap-4">
                {!configured ? (canManage ? <Button className="w-fit" onClick={() => router.post(calendars.store().url, { google_calendar_id: calendar.id })}>Liberar para agendamento</Button> : <p className="text-muted-foreground text-sm">Você não tem permissão para liberar este Calendário.</p>) : <form onSubmit={save} className="grid gap-4">
                    <div className="grid gap-2 sm:grid-cols-2"><div className="grid gap-2"><Label>Nome de exibição</Label><Input value={name} onChange={(event) => setName(event.target.value)} disabled={!canManage} /></div><div className="grid gap-2"><Label>Descrição</Label><Input value={description} onChange={(event) => setDescription(event.target.value)} disabled={!canManage} /></div><div className="grid gap-2"><Label>Duração (minutos)</Label><Input type="number" min="5" value={duration} onChange={(event) => setDuration(event.target.value)} disabled={!canManage} /></div><div className="grid gap-2"><Label>Antecedência mínima (minutos)</Label><Input type="number" min="0" value={notice} onChange={(event) => setNotice(event.target.value)} disabled={!canManage} /></div></div>
                    <div className="grid gap-2"><Label>Janela própria (JSON)</Label><textarea value={hours} onChange={(event) => setHours(event.target.value)} disabled={!canManage} placeholder="Vazio = Horário de atendimento da Empresa" className="border-input bg-background min-h-28 rounded-md border px-3 py-2 font-mono text-xs" /><p className="text-muted-foreground text-xs">Use os dias monday a sunday com enabled, open e close. Deixe vazio para usar o horário da Empresa.</p></div>
                    {canManage && <div className="flex flex-wrap gap-2"><Button type="submit">Salvar regras</Button><Button type="button" variant="outline" onClick={() => router.delete(calendars.destroy(configured.id).url)}>Deixar de liberar</Button></div>}
                </form>}
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status, label }: { status: string; label: string }) { return <Badge variant={status === 'connected' ? 'success' : status === 'error' ? 'destructive' : 'secondary'}>{status === 'connected' && <Check className="size-3" />}{label}</Badge>; }
