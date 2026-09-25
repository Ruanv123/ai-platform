import { Head, Link, router } from '@inertiajs/react';
import { FlaskConical, Trash2 } from 'lucide-react';
import {
    ChannelSignalLamp,
    channelSignal,
    formatChannelMoment,
} from '@/components/channel-readiness';
import WhatsAppTestAgentSelect from '@/components/channels/whatsapp-test-agent-select';
import WhatsAppTestQrCodePanel from '@/components/channels/whatsapp-test-qr-code-panel';
import type {
    AgentOption,
    WhatsAppTestChannelDetails,
} from '@/components/channels/whatsapp-test-channel-types';
import WhatsAppProfileTab from '@/components/channels/whatsapp-profile-tab';
import type { WhatsAppProfileCapabilities } from '@/components/channels/whatsapp-profile-types';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as channelsIndex } from '@/routes/channels';
import { destroy, edit as whatsappTestEdit } from '@/routes/channels/whatsapp-test';
import type { TeamPermissions } from '@/types';

type Props = {
    activeTab: 'conexao' | 'perfil';
    whatsappTest: WhatsAppTestChannelDetails | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
    whatsappProfileCapabilities: WhatsAppProfileCapabilities;
};

export default function WhatsAppTest({
    activeTab,
    whatsappTest,
    agents,
    permissions,
    whatsappProfileCapabilities,
}: Props) {
    const canManage = permissions.canManageChannels;
    const isConnected = whatsappTest?.status === 'connected';
    const connectionUrl = whatsappTestEdit({ query: { tab: 'conexao' } }).url;
    const profileUrl = whatsappTestEdit({ query: { tab: 'perfil' } }).url;

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

                <div className="grid gap-6">
                    <div
                        role="tablist"
                        aria-label="Configuração do WhatsApp de testes"
                        className="flex border-b"
                    >
                        <Link
                            href={connectionUrl}
                            role="tab"
                            aria-selected={activeTab === 'conexao'}
                            className={`-mb-px border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                activeTab === 'conexao'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Conexão
                        </Link>
                        <Link
                            href={profileUrl}
                            role="tab"
                            aria-selected={activeTab === 'perfil'}
                            className={`-mb-px border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                activeTab === 'perfil'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            Perfil
                        </Link>
                    </div>

                    {activeTab === 'perfil' ? (
                        <WhatsAppProfileTab
                            capabilities={whatsappProfileCapabilities}
                            connectionUrl={connectionUrl}
                            channelId={whatsappTest?.id ?? null}
                            canEdit={false}
                        />
                    ) : whatsappTest === null ? (
                        <Card>
                            <CardContent className="text-muted-foreground text-sm">
                                Nenhuma integração criada ainda. Volte para
                                Canais e use "Adicionar canal" para criar uma.
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
                                            <WhatsAppTestAgentSelect
                                                whatsappTest={whatsappTest}
                                                agents={agents}
                                            />
                                        ) : (
                                            <p className="text-muted-foreground text-sm">
                                                Somente Owner e Admin alteram
                                                este Canal.
                                            </p>
                                        )
                                    ) : (
                                        <WhatsAppTestQrCodePanel
                                            whatsappTest={whatsappTest}
                                        />
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
                                            Evolution Go da plataforma. Se a
                                            conexão cair depois de conectada,
                                            remova esta integração e crie outra
                                            para gerar um novo QR code.
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
