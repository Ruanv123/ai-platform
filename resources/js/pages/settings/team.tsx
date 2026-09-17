import { Head, router } from '@inertiajs/react';
import { ChevronDown, LogOut, Mail, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import CopyInvitationLink from '@/components/copy-invitation-link';
import Heading from '@/components/heading';
import InviteMemberModal from '@/components/invite-member-modal';
import RemoveMemberModal from '@/components/remove-member-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { edit, leave } from '@/routes/team';
import { update as updateMember } from '@/routes/team/members';
import type {
    RoleOption,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    members: TeamMember[];
    invitations?: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
    canLeave: boolean;
};

export default function Team({
    members,
    invitations,
    permissions,
    availableRoles,
    canLeave,
}: Props) {
    const getInitials = useInitials();
    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<TeamMember | null>(
        null,
    );
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<TeamInvitation | null>(null);

    const updateMemberRole = (member: TeamMember, newRole: string) => {
        router.visit(updateMember(member.id), {
            data: { role: newRole },
            preserveScroll: true,
        });
    };

    const confirmRemoveMember = (member: TeamMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const confirmCancelInvitation = (invitation: TeamInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    const leaveTeam = () => {
        if (window.confirm('Tem certeza de que deseja sair desta Empresa?')) {
            router.delete(leave());
        }
    };

    return (
        <>
            <Head title="Equipe" />

            <h1 className="sr-only">Equipe</h1>

            <div className="flex flex-col gap-10">
                <section className="space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <Heading
                            variant="small"
                            title="Membros da equipe"
                            description={
                                permissions.canCreateInvitation
                                    ? 'Gerencie quem faz parte desta equipe'
                                    : 'Veja quem faz parte desta equipe'
                            }
                        />

                        {permissions.canCreateInvitation ? (
                            <Button
                                data-test="invite-member-button"
                                onClick={() => setInviteDialogOpen(true)}
                            >
                                <UserPlus /> Convidar membro
                            </Button>
                        ) : null}
                    </div>

                    <div className="space-y-3">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                data-test="member-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex min-w-0 items-center gap-4">
                                    <Avatar className="h-10 w-10">
                                        {member.avatar ? (
                                            <AvatarImage
                                                src={member.avatar}
                                                alt={member.name}
                                            />
                                        ) : null}
                                        <AvatarFallback>
                                            {getInitials(member.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">
                                            {member.name}
                                        </div>
                                        <div className="text-muted-foreground truncate text-sm">
                                            {member.email}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex shrink-0 items-center gap-2">
                                    {member.role !== 'owner' &&
                                    permissions.canUpdateMember ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    data-test="member-role-trigger"
                                                >
                                                    {member.role_label}
                                                    <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent>
                                                {availableRoles.map((role) => (
                                                    <DropdownMenuItem
                                                        key={role.value}
                                                        data-test="member-role-option"
                                                        onSelect={() =>
                                                            updateMemberRole(
                                                                member,
                                                                role.value,
                                                            )
                                                        }
                                                    >
                                                        {role.label}
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : (
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                    )}

                                    {member.role !== 'owner' &&
                                    permissions.canRemoveMember ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="member-remove-button"
                                                        onClick={() =>
                                                            confirmRemoveMember(
                                                                member,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Remover membro</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {invitations ? (
                    <section className="space-y-6">
                        <Heading
                            variant="small"
                            title="Convites pendentes"
                            description="Convites que ainda não foram aceitos"
                        />

                        {invitations.length > 0 ? (
                            <div className="space-y-3">
                                {invitations.map((invitation) => (
                                    <div
                                        key={invitation.code}
                                        data-test="invitation-row"
                                        className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-center gap-4">
                                            <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                                <Mail className="text-muted-foreground h-5 w-5" />
                                            </div>
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">
                                                    {invitation.email}
                                                </div>
                                                <div className="text-muted-foreground text-sm">
                                                    {invitation.role_label}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                            {permissions.canCreateInvitation ? (
                                                <CopyInvitationLink
                                                    code={invitation.code}
                                                />
                                            ) : null}

                                            {permissions.canCancelInvitation ? (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                data-test="invitation-cancel-button"
                                                                onClick={() =>
                                                                    confirmCancelInvitation(
                                                                        invitation,
                                                                    )
                                                                }
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>
                                                                Cancelar convite
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            ) : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Nenhum convite pendente.
                            </p>
                        )}
                    </section>
                ) : null}

                {canLeave ? (
                    <section className="border-destructive/30 flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div>
                            <p className="font-medium">Sair da Empresa</p>
                            <p className="text-muted-foreground text-sm">
                                Você perderá o acesso à equipe e aos dados desta
                                Empresa.
                            </p>
                        </div>
                        <Button variant="destructive" onClick={leaveTeam}>
                            <LogOut /> Sair
                        </Button>
                    </section>
                ) : null}
            </div>

            {permissions.canCreateInvitation ? (
                <InviteMemberModal
                    availableRoles={availableRoles}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <RemoveMemberModal
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            <CancelInvitationModal
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />
        </>
    );
}

Team.layout = {
    breadcrumbs: [
        {
            title: 'Equipe',
            href: edit(),
        },
    ],
};
