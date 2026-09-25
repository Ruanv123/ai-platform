import { Deferred, usePage } from '@inertiajs/react';
import WhatsAppProfileSkeleton from '@/components/channels/whatsapp-profile-skeleton';
import WhatsAppProfileView from '@/components/channels/whatsapp-profile-view';
import type {
    WhatsAppProfileCapabilities,
    WhatsAppProfileResult,
} from '@/components/channels/whatsapp-profile-types';

type Props = {
    capabilities: WhatsAppProfileCapabilities;
    connectionUrl: string;
};

export default function WhatsAppProfileTab({
    capabilities,
    connectionUrl,
}: Props) {
    const { whatsappProfile } = usePage<{
        whatsappProfile?: WhatsAppProfileResult | null;
    }>().props;

    return (
        <Deferred
            data="whatsappProfile"
            fallback={<WhatsAppProfileSkeleton />}
        >
            {() =>
                whatsappProfile ? (
                    <WhatsAppProfileView
                        profile={whatsappProfile}
                        capabilities={capabilities}
                        connectionUrl={connectionUrl}
                    />
                ) : (
                    <WhatsAppProfileSkeleton />
                )
            }
        </Deferred>
    );
}
