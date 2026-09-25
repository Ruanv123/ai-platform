import { Head, Link } from '@inertiajs/react';
import WhatsAppConnectionPanel from '@/components/channels/whatsapp-connection-panel';
import type {
    AgentOption,
    WhatsAppChannelDetails,
} from '@/components/channels/whatsapp-channel-types';
import WhatsAppProfileTab from '@/components/channels/whatsapp-profile-tab';
import type { WhatsAppProfileCapabilities } from '@/components/channels/whatsapp-profile-types';
import Heading from '@/components/heading';
import { index as channelsIndex } from '@/routes/channels';
import { edit as whatsappEdit } from '@/routes/channels/whatsapp';
import type { TeamPermissions } from '@/types';

type Props = {
    activeTab: 'conexao' | 'perfil';
    whatsapp: WhatsAppChannelDetails | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
    webhookUrl: string;
    whatsappProfileCapabilities: WhatsAppProfileCapabilities;
};

export default function WhatsApp({
    activeTab,
    whatsapp,
    agents,
    permissions,
    webhookUrl,
    whatsappProfileCapabilities,
}: Props) {
    const connectionUrl = whatsappEdit({ query: { tab: 'conexao' } }).url;
    const profileUrl = whatsappEdit({ query: { tab: 'perfil' } }).url;

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

                <div className="grid gap-6">
                    <div
                        role="tablist"
                        aria-label="Configuração do WhatsApp"
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

                    {activeTab === 'conexao' ? (
                        <WhatsAppConnectionPanel
                            whatsapp={whatsapp}
                            agents={agents}
                            permissions={permissions}
                            webhookUrl={webhookUrl}
                        />
                    ) : (
                        <WhatsAppProfileTab
                            capabilities={whatsappProfileCapabilities}
                            connectionUrl={connectionUrl}
                        />
                    )}
                </div>
            </div>
        </>
    );
}
