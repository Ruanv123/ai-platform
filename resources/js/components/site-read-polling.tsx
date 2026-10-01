import { usePoll } from '@inertiajs/react';

export default function SiteReadPolling() {
    usePoll(3000, { only: ['siteRead'] }, { mode: 'rest' });

    return null;
}
