import { Head, Link } from '@inertiajs/react';
import { Plus, Radio } from 'lucide-react';
import { useState } from 'react';
import {
    ChannelSignalLamp,
    channelSignal,
} from '@/components/channel-readiness';
import {
    channelIcons,
    type ChannelIcon,
    type ChannelTypeOption,
} from '@/components/channels/channel-catalog';
import { ChannelCreateDialog } from '@/components/channels/channel-create-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { TeamPermissions } from '@/types';

type Channel = {
    id: string;
    label: string;
    description: string;
    icon: ChannelIcon;
    destination: string | null;
    status: 'not_configured' | 'connected' | 'error';
    statusLabel: string;
    lastError: string | null;
    displayPhoneNumber: string | null;
    verifiedName: string | null;
    agent: { id: string; name: string } | null;
};

type AgentOption = {
    id: string;
    name: string;
};

type Props = {
    channels: Channel[];
    channelTypes: ChannelTypeOption[];
    agents: AgentOption[];
    permissions: TeamPermissions;
};

function ChannelCard({
    channel,
    canManage,
}: {
    channel: Channel;
    canManage: boolean;
}) {
    const Icon = channelIcons[channel.icon];
    const signal = channelSignal({
        status: channel.status,
        statusLabel: channel.statusLabel,
        lastError: channel.lastError,
        credentialsSavedAt: null,
        webhookVerifiedAt: null,
        lastTestedAt: null,
        agent: channel.agent,
    });

    const identity = [channel.displayPhoneNumber, channel.verifiedName]
        .filter((part): part is string => part !== null)
        .join(' · ');

    const footerText =
        channel.status === 'connected' && channel.agent === null
            ? 'Conectado, mas sem Agente vinculado.'
            : channel.agent !== null
              ? `Atendido por ${channel.agent.name}.`
              : 'Nenhum Agente vinculado ainda.';

    return (
        <article className="bg-card flex flex-col gap-4 rounded-xl border p-5 shadow-xs">
            <header className="flex items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-md">
                        <Icon className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <h2 className="font-semibold tracking-tight">
                            {channel.label}
                        </h2>
                        <p className="text-muted-foreground truncate text-sm tabular-nums">
                            {identity === '' ? channel.description : identity}
                        </p>
                    </div>
                </div>
                <ChannelSignalLamp signal={signal} />
            </header>

            {channel.lastError !== null && (
                <p className="text-destructive text-sm">{channel.lastError}</p>
            )}

            <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p className="text-muted-foreground text-sm">{footerText}</p>
                {canManage && channel.destination !== null && (
                    <Button variant="outline" asChild>
                        <Link href={channel.destination} prefetch>
                            Editar
                        </Link>
                    </Button>
                )}
            </footer>
        </article>
    );
}

function EmptyState({
    canManage,
    onAddChannel,
}: {
    canManage: boolean;
    onAddChannel: () => void;
}) {
    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-12 text-center">
            <span className="bg-muted flex size-12 items-center justify-center rounded-full">
                <Radio className="text-muted-foreground size-5" />
            </span>
            <div className="grid gap-1">
                <h2 className="font-semibold tracking-tight">
                    Nenhum Canal configurado
                </h2>
                <p className="text-muted-foreground max-w-sm text-sm">
                    Conecte um meio de atendimento para que seus Agentes comecem
                    a responder por ele.
                </p>
            </div>
            {canManage && (
                <Button onClick={onAddChannel}>
                    <Plus />
                    Adicionar canal
                </Button>
            )}
        </div>
    );
}

export default function Channels({
    channels,
    channelTypes,
    agents,
    permissions,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const canManage = permissions.canManageChannels;
    const canAddChannel = channelTypes.some(
        (type) => type.available && !type.configured,
    );

    return (
        <>
            <Head title="Canais" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Canais"
                        description="Veja por onde sua Empresa atende e qual Agente responde em cada meio."
                    />
                    {canManage && channels.length > 0 && canAddChannel && (
                        <Button onClick={() => setDialogOpen(true)}>
                            <Plus />
                            Adicionar canal
                        </Button>
                    )}
                </div>

                {channels.length === 0 ? (
                    <EmptyState
                        canManage={canManage}
                        onAddChannel={() => setDialogOpen(true)}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {channels.map((channel) => (
                            <ChannelCard
                                key={channel.id}
                                channel={channel}
                                canManage={canManage}
                            />
                        ))}
                    </div>
                )}
            </div>

            <ChannelCreateDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                channelTypes={channelTypes}
                agents={agents}
                canManage={canManage}
            />
        </>
    );
}
