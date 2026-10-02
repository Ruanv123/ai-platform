import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { getEcho } from '@/lib/echo';

export function useInboxRealtime(teamId: number | null, only: string[]): void {
    const onlyKey = only.join(',');

    useEffect(() => {
        if (teamId === null) {
            return;
        }

        const echo = getEcho();
        if (echo === null) {
            return;
        }

        const channelName = `team.${teamId}.inbox`;
        const reloadOnly = onlyKey.split(',');
        const channel = echo.private(channelName);
        const reload = () => {
            router.reload({
                only: reloadOnly,
            });
        };
        const notifyCrm = (event: { message?: string }) => {
            if (event.message) {
                toast.info(event.message);
            }

            reload();
        };
        let hasConnected = echo.connectionStatus() === 'connected';
        let wasConnected = hasConnected;
        const stopConnectionUpdates = echo.connector.onConnectionChange((status) => {
            const reconnected = status === 'connected' && hasConnected && !wasConnected;

            if (status === 'connected') {
                hasConnected = true;
            }

            wasConnected = status === 'connected';

            if (reconnected) {
                reload();
            }
        });

        channel.listen('.human-conversation.updated', reload);
        channel.listen('.inbox-message.created', reload);
        channel.listen('.crm.notification', notifyCrm);

        return () => {
            channel.stopListening('.human-conversation.updated', reload);
            channel.stopListening('.inbox-message.created', reload);
            channel.stopListening('.crm.notification', notifyCrm);
            stopConnectionUpdates();
            echo.leave(channelName);
        };
    }, [teamId, onlyKey]);
}
