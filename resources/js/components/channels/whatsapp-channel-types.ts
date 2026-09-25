import type { ChannelReadiness } from '@/components/channel-readiness';
import type { WhatsAppChannel } from '@/components/channels/whatsapp-connection-form';

export type AgentOption = {
    id: string;
    name: string;
};

export type WhatsAppChannelDetails = ChannelReadiness &
    WhatsAppChannel & {
        verifyToken: string | null;
        method: string;
        displayPhoneNumber: string | null;
        verifiedName: string | null;
        qualityRating: string | null;
        subscribedAt: string | null;
        registeredAt: string | null;
        nameStatus: string | null;
        messagingLimitTier: string | null;
    };
