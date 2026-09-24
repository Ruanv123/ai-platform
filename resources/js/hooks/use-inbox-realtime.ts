import { router } from '@inertiajs/react';
import { useEffect } from 'react';
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

        return () => {
            channel.stopListening('.human-conversation.updated', reload);
            channel.stopListening('.inbox-message.created', reload);
            stopConnectionUpdates();
            echo.leave(channelName);
        };
    }, [teamId, onlyKey]);
}
