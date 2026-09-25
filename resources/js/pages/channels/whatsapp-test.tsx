import { Head, Link, router, usePoll } from '@inertiajs/react';
import { FlaskConical, Trash2 } from 'lucide-react';
import { useState, type SyntheticEvent } from 'react';
import {
    ChannelSignalLamp,
    channelSignal,
    formatChannelMoment,
    type ChannelReadiness,
} from '@/components/channel-readiness';
import type { WhatsAppTestChannel } from '@/components/channels/whatsapp-test-connection-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as channelsIndex } from '@/routes/channels';
import { destroy, update } from '@/routes/channels/whatsapp-test';
import type { TeamPermissions } from '@/types';

type AgentOption = {
    id: string;
    name: string;
};

type WhatsAppTestChannelDetails = ChannelReadiness & WhatsAppTestChannel;

type Props = {
    whatsappTest: WhatsAppTestChannelDetails | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
};

function logQrCodeLoaded(event: SyntheticEvent<HTMLImageElement>): void {
    const image = event.currentTarget;
    const mimeType = /^data:(image\/[^;,]+);base64,/i.exec(image.currentSrc)?.[1] ?? null;

    console.info('[WHATSAPP-TEST-QR] image.loaded', {
        mime_type: mimeType,
        width: image.naturalWidth,
        height: image.naturalHeight,
        source_length: image.currentSrc.length,
    });
}

function logQrCodeFailed(event: SyntheticEvent<HTMLImageElement>): void {
    const image = event.currentTarget;
    const mimeType = /^data:(image\/[^;,]+);base64,/i.exec(image.currentSrc)?.[1] ?? null;

    console.error('[WHATSAPP-TEST-QR] image.failed', {
        mime_type: mimeType,
        source_length: image.currentSrc.length,
    });
}

/** Keeps refetching the Channel while the pairing session isn't connected yet. */
function ConnectionPoll() {
    usePoll(2000, { only: ['whatsappTest'] });

    return null;
}

function QrCodePanel({
    whatsappTest,
}: {
    whatsappTest: WhatsAppTestChannelDetails;
}) {
    return (
        <div className="grid gap-4">
            <ConnectionPoll />

            {whatsappTest.status === 'error' &&
                whatsappTest.lastError !== null && (
                    <p className="text-destructive text-sm">
                        {whatsappTest.lastError}
                    </p>
                )}

            <div className="bg-muted flex aspect-square w-full max-w-64 items-center justify-center justify-self-center overflow-hidden rounded-lg border">
                {whatsappTest.qrcode !== null ? (
                    <img
                        src={whatsappTest.qrcode}
                        alt="QR code de pareamento do WhatsApp"
                        className="size-full object-contain"
                        onLoad={logQrCodeLoaded}
                        onError={logQrCodeFailed}
                    />
                ) : (
                    <div className="size-3/4 animate-pulse rounded-md bg-black/10" />
                )}
            </div>

            <p className="text-muted-foreground text-center text-sm">
                {whatsappTest.qrcode !== null
                    ? 'No WhatsApp do aparelho de testes, abra Aparelhos conectados → Conectar um aparelho e escaneie o código acima.'
                    : 'Aguardando o Evolution Go gerar o QR code...'}
            </p>
        </div>
    );
}

function AgentSelect({
    whatsappTest,
    agents,
}: {
    whatsappTest: WhatsAppTestChannelDetails;
    agents: AgentOption[];
}) {
    const [agentId, setAgentId] = useState(
        whatsappTest.agent?.id.toString() ?? 'none',
    );

    const onChange = (value: string) => {
        setAgentId(value);
        router.patch(update(whatsappTest.id).url, {
            agent_id: value === 'none' ? '' : value,
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="agent_id">Agente vinculado</Label>
            <Select value={agentId} onValueChange={onChange}>
                <SelectTrigger id="agent_id">
                    <SelectValue placeholder="Nenhum Agente" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Nenhum Agente</SelectItem>
                    {agents.map((agent) => (
                        <SelectItem key={agent.id} value={agent.id.toString()}>
                            {agent.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export default function WhatsAppTest({
    whatsappTest,
    agents,
    permissions,
}: Props) {
    const canManage = permissions.canManageChannels;
    const isConnected = whatsappTest?.status === 'connected';

    const remove = () => {
        if (
            whatsappTest !== null &&
            window.confirm('Remover a configuração do WhatsApp de testes?')
        ) {
            router.delete(destroy(whatsappTest.id).url);
        }
    };

    return (
        <>
            <Head title="WhatsApp de testes" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <Link
                    href={channelsIndex()}
                    className="text-muted-foreground hover:text-foreground w-fit text-sm font-medium transition-colors"
                >
                    ← Voltar para Canais
                </Link>
                <Heading
                    title="WhatsApp de testes"
                    description="Conecte um número por um serviço não oficial para desenvolver o atendimento."
                />

                {whatsappTest === null ? (
                    <Card>
                        <CardContent className="text-muted-foreground text-sm">
                            Nenhuma integração criada ainda. Volte para Canais e
                            use "Adicionar canal" para criar uma.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="grid gap-1">
                                        <CardTitle className="text-base">
                                            {whatsappTest.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {isConnected
                                                ? 'Conectado ao Evolution Go da plataforma.'
                                                : 'Escaneie o QR code para conectar este número.'}
                                        </CardDescription>
                                    </div>
                                    <FlaskConical className="text-muted-foreground size-5" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                {isConnected ? (
                                    canManage ? (
                                        <AgentSelect
                                            whatsappTest={whatsappTest}
                                            agents={agents}
                                        />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            Somente Owner e Admin alteram este
                                            Canal.
                                        </p>
                                    )
                                ) : (
                                    <QrCodePanel whatsappTest={whatsappTest} />
                                )}
                            </CardContent>
                        </Card>

                        <div className="grid content-start gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Estado do Canal
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <ChannelSignalLamp
                                        signal={channelSignal(whatsappTest)}
                                    />
                                    {whatsappTest.credentialsSavedAt !==
                                        null && (
                                        <p className="text-muted-foreground text-xs">
                                            Criado em{' '}
                                            {formatChannelMoment(
                                                whatsappTest.credentialsSavedAt,
                                            )}
                                        </p>
                                    )}
                                    {canManage && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive justify-start px-0"
                                            onClick={remove}
                                        >
                                            <Trash2 />
                                            Remover configuração
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Escopo desta tela
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-sm leading-relaxed">
                                        A instância é criada e pareada pelo
                                        Evolution Go da plataforma. Se a conexão
                                        cair depois de conectada, remova esta
                                        integração e crie outra para gerar um
                                        novo QR code.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
