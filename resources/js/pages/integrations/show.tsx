import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ShopifyConnectionPanel from '@/components/integrations/shopify-connection-panel';
import CrmConnectionPanel from '@/components/integrations/crm-connection-panel';
import agenda from '@/routes/integrations/agenda';
import calendars from '@/routes/integrations/agenda/calendars';
import { destroy, index, show, store, test, update } from '@/routes/integrations';
import type { TeamPermissions } from '@/types';

type CatalogIntegration = {
    value: string;
    name: string;
    description: string;
    logo: string;
    capabilities: string[];
    planned: boolean;
    available: boolean;
};

type ConfiguredIntegration = {
    id: string;
    name: string;
    type: string;
    provider: string | null;
    accountEmail?: string | null;
    crmMetadataLoadedAt?: string | null;
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

type ShopifyStore = {
    id: string;
    domain: string;
    name: string;
    currencyCode: string;
    countryCode: string | null;
    timezone: string | null;
    syncStatus: string;
    syncStatusLabel: string;
    syncProgress: number;
    syncProcessedCount: number;
    syncTotalCount: number | null;
    lastSyncedAt: string | null;
    syncError: string | null;
    suggestedAction: string | null;
};

type IntegrationType = 'crm' | 'ecommerce' | 'agenda';

type Props = {
    type: IntegrationType;
    catalog: CatalogIntegration[];
    provider: string | null;
    integration: ConfiguredIntegration | null;
    googleCalendars: GoogleCalendar[];
    schedulableCalendars: SchedulableCalendar[];
    agendaError: string | null;
    shop: ShopifyStore | null;
    shopifyError: string | null;
    shopifySuccess: string | null;
    crmError: string | null;
    crmSuccess: string | null;
    crmFields: { id: string; name: string }[];
    crmOwners: { id: string; name: string }[];
    defaultCrmOwnerId: string | null;
    visibleCrmFieldIds: string[];
    registerAttendedContacts: boolean;
    minimumAttendedContactMessages: number;
    permissions: TeamPermissions;
};

const settingsTabs = [
    { value: 'settings', label: 'Configurações' },
    { value: 'data', label: 'Dados' },
] as const;

type SettingsTab = (typeof settingsTabs)[number]['value'];

export default function IntegrationShowPage(props: Props) {
    const { type, catalog, provider, integration, permissions } = props;
    const [activeTab, setActiveTab] = useState<SettingsTab>('settings');
    const selectedProviderValue = integration?.provider ?? provider;
    const selectedCatalogItem = catalog.find((item) => item.value === selectedProviderValue);

    if (type === 'ecommerce' && (provider === 'shopify' || integration?.provider === 'shopify')) {
        return (
            <>
                <Head title="Shopify" />
                <ShopifyConnectionPanel
                    integration={integration}
                    shop={props.shop}
                    shopifyError={props.shopifyError}
                    shopifySuccess={props.shopifySuccess}
                    permissions={permissions}
                />
            </>
        );
    }

    if (type === 'agenda') {
        return <AgendaPage {...props} />;
    }

    if (type === 'crm') {
        return <>
            <Head title="RD Station CRM" />
            <CrmConnectionPanel
                key={integration?.id ?? 'crm-disconnected'}
                integration={integration}
                crmError={props.crmError}
                crmSuccess={props.crmSuccess}
                crmFields={props.crmFields}
                crmOwners={props.crmOwners}
                defaultCrmOwnerId={props.defaultCrmOwnerId}
                visibleCrmFieldIds={props.visibleCrmFieldIds}
                registerAttendedContacts={props.registerAttendedContacts}
                minimumAttendedContactMessages={props.minimumAttendedContactMessages}
                permissions={permissions}
            />
        </>;
    }

    const title = integration ? (selectedCatalogItem?.name ?? integration.name) : (selectedCatalogItem?.name ?? 'Conectar integração');

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader title={title} description={selectedCatalogItem?.description ?? 'A credencial é cifrada e nunca aparece no desenho do Fluxo.'} />
                {!selectedCatalogItem ? (
                    <ProviderPicker type={type} catalog={catalog} />
                ) : (
                    <div className="grid gap-5">
                        <div className="border-border flex gap-6 border-b" role="tablist" aria-label="Configurações da integração">
                            {settingsTabs.map((tab) => (
                                <button key={tab.value} type="button" role="tab" aria-selected={activeTab === tab.value} onClick={() => setActiveTab(tab.value)} className={`border-b-2 px-1 pb-3 text-sm font-medium transition-colors ${activeTab === tab.value ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'}`}>
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                        {activeTab === 'settings' ? <SettingsForm type={type} provider={selectedCatalogItem.value} integration={integration} permissions={permissions} /> : <Card className="border-dashed shadow-none"><CardContent className="text-muted-foreground flex min-h-40 items-center justify-center p-6 text-center text-sm">Em breve: visualize aqui os dados trazidos por esta integração.</CardContent></Card>}
                    </div>
                )}
            </div>
        </>
    );
}

function PageHeader({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar para integrações"><Link href={index()}><ArrowLeft /></Link></Button>
            <Heading title={title} description={description} />
        </div>
    );
}

function AgendaPage({ integration, googleCalendars, schedulableCalendars, agendaError, permissions }: Props) {
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

function ProviderPicker({ type, catalog }: { type: IntegrationType; catalog: CatalogIntegration[] }) { return <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{catalog.map((item) => <Card key={item.value} className={item.available ? 'group hover:border-primary/50' : 'bg-muted/20 border-dashed shadow-none'}><CardHeader className="flex-1"><span className="bg-primary text-primary-foreground flex size-12 shrink-0 items-center justify-center rounded-xl text-sm font-bold">{item.logo}</span><CardTitle className="pt-2">{item.name}</CardTitle><CardDescription>{item.description}</CardDescription></CardHeader><CardContent>{item.available ? <Button className="w-full" asChild><Link href={show(type, { query: { provider: item.value } })}>Conectar</Link></Button> : <span className="text-muted-foreground text-sm">Estamos preparando esta conexão.</span>}</CardContent></Card>)}</div>; }

function SettingsForm({ type, provider, integration, permissions }: { type: IntegrationType; provider: string; integration: ConfiguredIntegration | null; permissions: TeamPermissions }) {
    const [name, setName] = useState(integration?.name ?? '');
    const [baseUrl, setBaseUrl] = useState('');
    const [token, setToken] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); if (name.trim() === '' || !permissions.canManageIntegrations) return; setIsSaving(true); const payload = { name: name.trim(), type, provider, base_url: baseUrl, auth_type: token ? 'bearer' : integration === null ? 'none' : null, token: token || null }; const request = integration === null ? router.post(store().url, payload, { onFinish: () => setIsSaving(false) }) : router.patch(update(integration.id).url, payload, { onFinish: () => setIsSaving(false) }); return request; };
    return <Card><form onSubmit={submit}><CardContent className="grid gap-4 pt-6"><div className="grid gap-2"><Label htmlFor="integration-name">Nome da conexão</Label><Input id="integration-name" value={name} onChange={(event) => setName(event.target.value)} /><InputError message={undefined} /></div><div className="grid gap-2"><Label htmlFor="integration-url">URL base</Label><Input id="integration-url" type="url" value={baseUrl} onChange={(event) => setBaseUrl(event.target.value)} /></div><div className="grid gap-2"><Label htmlFor="integration-token">Token (opcional)</Label><Input id="integration-token" type="password" value={token} onChange={(event) => setToken(event.target.value)} /></div></CardContent><div className="flex items-center justify-between gap-2 border-t p-6"><div className="flex gap-2">{integration && permissions.canManageIntegrations && <><Button type="button" variant="outline" onClick={() => router.post(test(integration.id).url)}>Testar conexão</Button><Button type="button" variant="outline" onClick={() => router.delete(destroy(integration.id).url)}><Trash2 /> Remover</Button></>}</div><Button type="submit" disabled={isSaving || !permissions.canManageIntegrations}>{isSaving ? 'Salvando...' : integration === null ? 'Conectar' : 'Salvar alterações'}</Button></div></form></Card>;
}

function StatusBadge({ status, label }: { status: string; label: string }) { return <Badge variant={status === 'connected' ? 'success' : status === 'error' ? 'destructive' : 'secondary'}>{status === 'connected' && <Check className="size-3" />}{label}</Badge>; }
