import CompanyOnboardingManual from '@/components/company-onboarding-manual';
import CompanyOnboardingObjective from '@/components/company-onboarding-objective';
import CompanyOnboardingProgress from '@/components/company-onboarding-progress';
import CompanyOnboardingProposal from '@/components/company-onboarding-proposal';
import CompanyOnboardingStart from '@/components/company-onboarding-start';
import type { CompanyOption } from '@/types';

type OnboardingStep =
    | 'name_website'
    | 'manual_company_details'
    | 'site_read'
    | 'proposal_review'
    | 'agent_objective';

type Team = {
    name: string;
    website: string | null;
};

type SiteRead = {
    status: 'in_progress' | 'completed' | 'failed';
    statusLabel: string;
    foundPagesCount: number;
    readPagesCount: number;
    failedPagesCount: number;
    errorMessage: string | null;
};

type ProposalField = {
    value: unknown;
    sourceUrl: string;
};

type AgentObjectiveOption = {
    value: string;
    label: string;
    description: string;
};

type Props = {
    step: OnboardingStep;
    team: Team | null;
    segments: CompanyOption[];
    objectives: AgentObjectiveOption[];
    timezones: string[];
    weekdays: CompanyOption[];
    siteRead: SiteRead | null;
    proposal: { fields: Record<string, ProposalField> } | null;
    manualError: string | null;
};

export default function CompanyOnboarding({
    step,
    team,
    segments,
    objectives,
    timezones,
    weekdays,
    siteRead,
    proposal,
    manualError,
}: Props) {
    if (step === 'name_website') {
        return <CompanyOnboardingStart name={team?.name ?? ''} />;
    }

    if (step === 'site_read') {
        return (
            <CompanyOnboardingProgress
                siteRead={siteRead}
                teamName={team?.name ?? 'sua Empresa'}
            />
        );
    }

    if (step === 'proposal_review' && proposal !== null) {
        return (
            <CompanyOnboardingProposal
                name={team?.name ?? 'sua Empresa'}
                fields={proposal.fields}
                segments={segments}
                weekdays={weekdays}
            />
        );
    }

    if (step === 'agent_objective') {
        return (
            <CompanyOnboardingObjective
                name={team?.name ?? 'sua Empresa'}
                isSitePath={team !== null && team.website !== null}
                objectives={objectives}
            />
        );
    }

    return (
        <CompanyOnboardingManual
            name={team?.name ?? 'sua Empresa'}
            isSitePath={team !== null && team.website !== null}
            manualError={manualError}
            segments={segments}
            timezones={timezones}
            weekdays={weekdays}
        />
    );
}
