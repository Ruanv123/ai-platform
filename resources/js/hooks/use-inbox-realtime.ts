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

        channel.listen('.human-conversation.updated', reload);
        channel.listen('.inbox-message.created', reload);

        return () => {
            channel.stopListening('.human-conversation.updated', reload);
            channel.stopListening('.inbox-message.created', reload);
            echo.leave(channelName);
        };
    }, [teamId, onlyKey]);
}
