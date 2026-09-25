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
    updateTextsUrl: string | null;
    updateProfilePictureUrl: string | null;
    appId: string | null;
    requiresAppId: boolean;
    canEdit: boolean;
    categories?: WhatsAppProfileCategoryOption[];
};

export default function WhatsAppProfileTab({
    capabilities,
    connectionUrl,
    channelId,
    updateTextsUrl,
    updateProfilePictureUrl,
    appId,
    requiresAppId,
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
                        updateTextsUrl={updateTextsUrl}
                        updateProfilePictureUrl={updateProfilePictureUrl}
                        appId={appId}
                        requiresAppId={requiresAppId}
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
