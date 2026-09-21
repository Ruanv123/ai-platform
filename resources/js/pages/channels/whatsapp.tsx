import { Head, Link, router } from '@inertiajs/react';
import { Check, Clipboard, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    ChannelReadinessRail,
    ChannelSignalLamp,
    channelMilestones,
    channelNextStep,
    channelSignal,
    formatChannelMoment,
    type ChannelReadiness,
} from '@/components/channel-readiness';
import {
    WhatsAppConnectionForm,
    type WhatsAppChannel,
} from '@/components/channels/whatsapp-connection-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as channelsIndex } from '@/routes/channels';
import { destroy, test as testConnection } from '@/routes/channels/whatsapp';
import type { TeamPermissions } from '@/types';

type AgentOption = {
    id: string;
    name: string;
};

type WhatsAppChannelDetails = ChannelReadiness &
    WhatsAppChannel & {
        verifyToken: string | null;
        method: string;
        displayPhoneNumber: string | null;
        verifiedName: string | null;
        qualityRating: string | null;
    };

type Props = {
    whatsapp: WhatsAppChannelDetails | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
    webhookUrl: string;
};

/** The readiness of a Canal that has no saved credentials yet. */
const emptyReadiness: ChannelReadiness = {
    status: 'not_configured',
    statusLabel: 'Não conectado',
    lastError: null,
    credentialsSavedAt: null,
    webhookVerifiedAt: null,
    lastTestedAt: null,
    agent: null,
};

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? <Check /> : <Clipboard />}
            {copied ? 'Copiado' : label}
        </Button>
    );
}

function NumberFacts({ whatsapp }: { whatsapp: WhatsAppChannelDetails }) {
    const facts = [
        { label: 'Número', value: whatsapp.displayPhoneNumber },
        { label: 'Nome verificado', value: whatsapp.verifiedName },
        { label: 'Qualidade', value: whatsapp.qualityRating },
    ];

    return (
        <dl className="grid gap-3">
            {facts.map((fact) => (
                <div
                    key={fact.label}
                    className="flex items-baseline justify-between gap-3"
                >
                    <dt className="text-muted-foreground text-sm">
                        {fact.label}
                    </dt>
                    <dd className="text-sm font-medium tabular-nums">
                        {fact.value ?? '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function WhatsApp({
    whatsapp,
    agents,
    permissions,
    webhookUrl,
}: Props) {
    const canManage = permissions.canManageChannels;
    const readiness = whatsapp ?? emptyReadiness;
    const signal = channelSignal(readiness);
    const milestones = channelMilestones(readiness);
    const nextStep = channelNextStep(milestones);

    const test = () => {
        if (whatsapp !== null) {
            router.post(testConnection(whatsapp.id).url);
        }
    };

    const remove = () => {
        if (
            whatsapp !== null &&
            window.confirm('Remover a configuração deste número do WhatsApp?')
        ) {
            router.delete(destroy(whatsapp.id).url);
        }
    };

    return (
        <>
            <Head title="Configuração do WhatsApp" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <Link
                    href={channelsIndex()}
                    className="text-muted-foreground hover:text-foreground w-fit text-sm font-medium transition-colors"
                >
                    ← Voltar para Canais
                </Link>
                <Heading
                    title="WhatsApp"
                    description="Conecte o número oficial da sua Empresa à Meta."
                />

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="grid gap-1">
                                        <CardTitle className="text-base">
                                            Prontidão do Canal
                                        </CardTitle>
                                        <CardDescription>
                                            {nextStep.primary
                                                ? 'Falta um passo para este Canal atender.'
                                                : 'Este Canal está atendendo.'}
                                        </CardDescription>
                                    </div>
                                    <ChannelSignalLamp signal={signal} />
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <ChannelReadinessRail milestones={milestones} />

                                {whatsapp !== null && (
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                                        <p className="text-muted-foreground text-sm">
                                            Último teste:{' '}
                                            {formatChannelMoment(
                                                whatsapp.lastTestedAt,
                                            ) ?? 'ainda não realizado'}
                                        </p>
                                        {canManage && (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={test}
                                                >
                                                    <RefreshCw />
                                                    Testar conexão
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={remove}
                                                >
                                                    <Trash2 />
                                                    Remover número
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Credenciais da Meta
                                </CardTitle>
                                <CardDescription>
                                    Copie estes valores do painel do aplicativo
                                    Meta e do WhatsApp Manager.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WhatsAppConnectionForm
                                    whatsapp={whatsapp}
                                    agents={agents}
                                    canManage={canManage}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Webhook da Meta
                                </CardTitle>
                                <CardDescription>
                                    Cole estes dados no painel do aplicativo
                                    Meta. A verificação chega sozinha.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {whatsapp === null ? (
                                    <p className="text-muted-foreground text-sm">
                                        Salve as credenciais para gerar o verify
                                        token e liberar os dados do webhook.
                                    </p>
                                ) : (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook_url">
                                                URL do webhook
                                            </Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    id="webhook_url"
                                                    value={webhookUrl}
                                                    readOnly
                                                />
                                                <CopyButton
                                                    value={webhookUrl}
                                                    label="Copiar"
                                                />
                                            </div>
                                        </div>
                                        {canManage &&
                                            whatsapp.verifyToken !== null && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="verify_token">
                                                        Verify token
                                                    </Label>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            id="verify_token"
                                                            value={
                                                                whatsapp.verifyToken
                                                            }
                                                            readOnly
                                                        />
                                                        <CopyButton
                                                            value={
                                                                whatsapp.verifyToken
                                                            }
                                                            label="Copiar"
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {whatsapp !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Número do WhatsApp
                                    </CardTitle>
                                    <CardDescription>
                                        O que a Meta informa sobre este número.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <NumberFacts whatsapp={whatsapp} />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
