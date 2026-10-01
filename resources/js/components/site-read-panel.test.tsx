import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import SiteReadPanel, {
    type LatestSiteRead,
} from '@/components/site-read-panel';

const latest: LatestSiteRead = {
    id: 1,
    website: 'https://linktr.ee/loja-aurora',
    resolvedWebsite: 'https://loja-aurora.example.test/',
    status: 'completed',
    statusLabel: 'Concluída',
    startedAt: '01/10/2026 10:00',
    completedAt: '01/10/2026 10:02',
    foundPagesCount: 1,
    readPagesCount: 1,
    failedPagesCount: 0,
    errorMessage: null,
    linkableAgents: [],
    pages: [],
};

afterEach(() => {
    cleanup();
});

describe('SiteReadPanel', () => {
    it('shows the submitted Linktree address and the resolved site address', () => {
        render(
            <SiteReadPanel
                website={latest.website}
                canStart={false}
                canUpdate={false}
                availableAgainAt={null}
                latest={latest}
            />,
        );

        expect(screen.getByText('Endereço informado:')).toBeInTheDocument();
        expect(screen.getByText(latest.website)).toBeInTheDocument();
        expect(screen.getByText('Site lido:')).toBeInTheDocument();
        expect(screen.getByText(latest.resolvedWebsite)).toBeInTheDocument();
    });
});
