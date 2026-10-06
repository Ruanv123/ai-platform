export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: number;
    name: string;
    slug: string;
    segment?: string | null;
    website?: string | null;
    businessDescription?: string | null;
    productsServices?: string | null;
    targetAudience?: string | null;
    businessHours?: Record<
        string,
        {
            enabled?: boolean | string;
            open?: string | null;
            close?: string | null;
        }
    > | null;
    timezone?: string | null;
    phone?: string | null;
    publicEmail?: string | null;
    address?: string | null;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type CompanyOption = {
    value: string;
    label: string;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canManageKnowledge: boolean;
    canManageAgents: boolean;
    canManageChannels: boolean;
    canManageIntegrations: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};
