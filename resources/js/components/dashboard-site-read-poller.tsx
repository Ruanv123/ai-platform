import { usePoll } from '@inertiajs/react';

export default function DashboardSiteReadPoller() {
    usePoll(5000, { only: ['siteReadReminder'] }, { mode: 'rest' });

    return null;
}
