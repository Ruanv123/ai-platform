import { usePoll } from '@inertiajs/react';

type ConditionalPollProps = {
    enabled: boolean;
};

export function ConditionalPoll({ enabled }: ConditionalPollProps) {
    if (!enabled) {
        return null;
    }

    return <ActivePoll />;
}

function ActivePoll() {
    usePoll(
        5000,
        {
            only: ['documents', 'polling', 'storage'],
        },
        { autoStart: true },
    );

    return null;
}
