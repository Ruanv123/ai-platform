import { Deferred, usePage } from '@inertiajs/react';
import WhatsAppProfileSkeleton from '@/components/channels/whatsapp-profile-skeleton';
import WhatsAppProfileView from '@/components/channels/whatsapp-profile-view';
import type {
    WhatsAppProfileCapabilities,
    WhatsAppProfileCategoryOption,
    WhatsAppProfileResult,
} from '@/components/channels/whatsapp-profile-types';

type Props = {
    capabilities: WhatsAppProfileCapabilities;
    connectionUrl: string;
    channelId: string | null;
    canEdit: boolean;
    categories?: WhatsAppProfileCategoryOption[];
};

export default function WhatsAppProfileTab({
    capabilities,
    connectionUrl,
    channelId,
    canEdit,
    categories = [],
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
                        channelId={channelId}
                        canEdit={canEdit}
                        categories={categories}
                    />
                ) : (
                    <WhatsAppProfileSkeleton />
                )
            }
        </Deferred>
    );
}
