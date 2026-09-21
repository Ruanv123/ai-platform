import { FlaskConical, Globe2, Instagram, MessageCircle } from 'lucide-react';

export type ChannelIcon =
    | 'message-circle'
    | 'flask-conical'
    | 'globe-2'
    | 'instagram';

export const channelIcons = {
    'message-circle': MessageCircle,
    'flask-conical': FlaskConical,
    'globe-2': Globe2,
    instagram: Instagram,
} as const satisfies Record<ChannelIcon, typeof MessageCircle>;

export type ChannelTypeOption = {
    value: string;
    label: string;
    description: string;
    icon: ChannelIcon;
    available: boolean;
    destination: string | null;
    configured: boolean;
};
