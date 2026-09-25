import type { ChannelReadiness } from '@/components/channel-readiness';
import type { WhatsAppTestChannel } from '@/components/channels/whatsapp-test-connection-form';

export type AgentOption = {
    id: string;
    name: string;
};

export type WhatsAppTestChannelDetails = ChannelReadiness & WhatsAppTestChannel;
