import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Globe2, Instagram, MessageCircle } from "lucide-react";
import {
    ChannelReadinessRail,
    ChannelSignalLamp,
    channelMilestones,
    channelNextStep,
    channelSignal,
    type ChannelReadiness,
} from "@/components/channel-readiness";
import Heading from "@/components/heading";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import type { TeamPermissions } from "@/types";

type Channel = ChannelReadiness & {
    value: string;
    label: string;
    description: string;
    icon: "message-circle" | "globe-2" | "instagram";
    available: boolean;
    destination: string | null;
    displayPhoneNumber: string | null;
    verifiedName: string | null;
    qualityRating: string | null;
    agentMissing: boolean;
};

type Props = {
    channels: Channel[];
    permissions: TeamPermissions;
};

const channelIcons = {
    "message-circle": MessageCircle,
    "globe-2": Globe2,
    instagram: Instagram,
} as const;

function GroupLabel({ children }: { children: string }) {
    return (
        <p className="text-muted-foreground px-1 text-xs font-medium tracking-wide uppercase">
            {children}
        </p>
    );
}

function OperatingChannelCard({
    channel,
    canManage,
}: {
    channel: Channel;
    canManage: boolean;
}) {
    const Icon = channelIcons[channel.icon];
    const signal = channelSignal(channel);
    const milestones = channelMilestones(channel);
    const nextStep = channelNextStep(milestones);

    const identity = [channel.displayPhoneNumber, channel.verifiedName]
        .filter((part): part is string => part !== null)
        .join(" · ");

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
                            {identity === "" ? channel.description : identity}
                        </p>
                    </div>
                </div>
                <ChannelSignalLamp signal={signal} />
            </header>

            <ChannelReadinessRail milestones={milestones} />

            <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p className="text-muted-foreground text-sm">
                    {channel.qualityRating === null
                        ? "A Meta informa a qualidade do número após o primeiro teste."
                        : `Qualidade ${channel.qualityRating.toLowerCase()}`}
                </p>
                {canManage && channel.destination !== null ? (
                    <Button
                        variant={nextStep.primary ? "default" : "outline"}
                        asChild
                    >
                        <Link href={channel.destination} prefetch>
                            {nextStep.label}
                            {nextStep.primary && <ArrowRight />}
                        </Link>
                    </Button>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        Somente Owner e Admin alteram este Canal.
                    </p>
                )}
            </footer>
        </article>
    );
}

function UpcomingChannelCard({ channel }: { channel: Channel }) {
    const Icon = channelIcons[channel.icon];

    return (
        <article className="flex flex-col gap-3 rounded-xl border border-dashed p-5">
            <header className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="text-muted-foreground flex size-6 shrink-0 items-center justify-center">
                        <Icon className="size-4" />
                    </span>
                    <h2 className="min-w-0 font-medium">{channel.label}</h2>
                </div>
                <Badge variant="secondary" className="shrink-0">
                    Em breve
                </Badge>
            </header>
            <p className="text-muted-foreground text-sm">
                {channel.description}
            </p>
        </article>
    );
}

export default function Channels({ channels, permissions }: Props) {
    const operating = channels.filter((channel) => channel.available);
    const upcoming = channels.filter((channel) => !channel.available);

    return (
        <>
            <Head title="Canais" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <Heading
                    title="Canais"
                    description="Veja por onde sua Empresa atende e qual Agente responde em cada meio."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
                    <section className="grid gap-3">
                        <GroupLabel>Em operação</GroupLabel>
                        <div className="grid gap-4">
                            {operating.map((channel) => (
                                <OperatingChannelCard
                                    key={channel.value}
                                    channel={channel}
                                    canManage={permissions.canManageChannels}
                                />
                            ))}
                        </div>
                    </section>

                    {upcoming.length > 0 && (
                        <section className="grid gap-3">
                            <GroupLabel>Em breve</GroupLabel>
                            <div className="grid gap-4">
                                {upcoming.map((channel) => (
                                    <UpcomingChannelCard
                                        key={channel.value}
                                        channel={channel}
                                    />
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}
