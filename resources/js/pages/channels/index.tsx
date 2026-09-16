import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Bot,
    Globe2,
    Instagram,
    MessageCircle,
    Settings2,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TeamPermissions } from '@/types';

type Agent = {
    id: number;
    name: string;
};

type Channel = {
    value: string;
    label: string;
    description: string;
    icon: 'message-circle' | 'globe-2' | 'instagram';
    available: boolean;
    destination: string | null;
    status: 'not_configured' | 'connected' | 'error' | null;
    statusLabel: string | null;
    lastError: string | null;
    displayPhoneNumber: string | null;
    agent: Agent | null;
    agentMissing: boolean;
};

type Props = {
    channels: Channel[];
    permissions: TeamPermissions;
};

const channelIcons = {
    'message-circle': MessageCircle,
    'globe-2': Globe2,
    instagram: Instagram,
} as const;

function channelStatusVariant(
    status: Channel['status'],
): 'default' | 'secondary' | 'destructive' {
    return status === 'connected'
        ? 'default'
        : status === 'error'
          ? 'destructive'
          : 'secondary';
}

function ChannelRow({
    channel,
    canManage,
}: {
    channel: Channel;
    canManage: boolean;
}) {
    const Icon = channelIcons[channel.icon];
    const description =
        channel.status === 'error' && channel.lastError
            ? channel.lastError
            : channel.description;
    const isWhatsApp = channel.value === 'whatsapp';

    return (
        <div
            className={`group grid gap-4 border-b py-5 last:border-b-0 md:grid-cols-[minmax(220px,1.1fr)_minmax(260px,1.5fr)_auto] md:items-center md:gap-8 ${
                channel.agentMissing ? 'bg-amber-500/5' : ''
            }`}
        >
            <div className="flex min-w-0 items-start gap-3">
                <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                    <Icon className="size-5" />
                </span>
                <div className="grid min-w-0 gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold">{channel.label}</h2>
                        {!channel.available && (
                            <Badge variant="secondary">Em breve</Badge>
                        )}
                    </div>
                    <p
                        className={`text-muted-foreground truncate text-sm ${
                            channel.status === 'error'
                                ? 'text-destructive dark:text-destructive'
                                : ''
                        }`}
                        title={description}
                    >
                        {description}
                    </p>
                </div>
            </div>

            {isWhatsApp ? (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid min-w-0 gap-1">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Status
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={channelStatusVariant(channel.status)}
                            >
                                {channel.statusLabel}
                            </Badge>
                            {channel.agentMissing && (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500/50 text-amber-700 dark:text-amber-400"
                                >
                                    <AlertTriangle />
                                    Sem Agente
                                </Badge>
                            )}
                        </div>
                    </div>
                    <div className="grid min-w-0 gap-1">
                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Número
                        </p>
                        <p className="truncate text-sm font-medium">
                            {channel.displayPhoneNumber ?? 'Ainda não testado'}
                        </p>
                        <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                            <Bot className="size-3.5 shrink-0" />
                            {channel.agent?.name ?? 'Nenhum Agente vinculado'}
                        </p>
                    </div>
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    Este meio de atendimento estará disponível em breve.
                </p>
            )}

            <div className="flex justify-start md:justify-end">
                {channel.available && canManage && channel.destination ? (
                    <Button variant="outline" asChild>
                        <Link href={channel.destination}>
                            {channel.status === 'not_configured' ? (
                                <>
                                    Conectar
                                    <ArrowRight />
                                </>
                            ) : (
                                <>
                                    <Settings2 />
                                    Configurar
                                </>
                            )}
                        </Link>
                    </Button>
                ) : (
                    <span className="text-muted-foreground text-sm">
                        {!channel.available
                            ? 'Indisponível'
                            : 'Somente visualização'}
                    </span>
                )}
            </div>
        </div>
    );
}

export default function Channels({ channels, permissions }: Props) {
    return (
        <>
            <Head title="Canais" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 md:p-6">
                <Heading
                    title="Canais"
                    description="Veja por onde sua Empresa atende e qual Agente responde em cada meio."
                />

                <section className="bg-card rounded-xl border px-4 shadow-xs md:px-6">
                    <div className="flex items-center justify-between gap-4 border-b py-4">
                        <div className="grid gap-1">
                            <h2 className="font-semibold">
                                Meios de atendimento
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                O estado de cada Canal aparece aqui, sem
                                precisar abrir sua configuração.
                            </p>
                        </div>
                    </div>

                    {channels.map((channel) => (
                        <ChannelRow
                            key={channel.value}
                            channel={channel}
                            canManage={permissions.canManageChannels}
                        />
                    ))}
                </section>
            </div>
        </>
    );
}
