import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

export function useConditionalPoll(shouldPoll: boolean): void {
    const { start, stop } = usePoll(
        5000,
        { only: ['documents', 'polling', 'storage'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (shouldPoll) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [shouldPoll, start, stop]);
}
