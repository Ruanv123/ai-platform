import { Form, Head, router } from '@inertiajs/react';
import { ChevronDown, LogOut, Mail, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { edit, update } from '@/routes/company';
import { update as updateMember } from '@/routes/teams/members';
import { leave } from '@/routes/teams';
import type {
    CompanyOption,
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
    segments: CompanyOption[];
    canLeave: boolean;
};

export default function TeamEdit({
    team,
    members,
    invitations,
    permissions,
    availableRoles,
    segments,
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

    const pageTitle = useMemo(
        () =>
            permissions.canUpdateTeam
                ? `Editar ${team.name}`
                : `Visualizar ${team.name}`,
        [permissions.canUpdateTeam, team.name],
    );

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
            <Head title={pageTitle} />

            <h1 className="sr-only">{pageTitle}</h1>

            <div className="flex flex-col space-y-10">
                <div className="space-y-6">
                    {permissions.canUpdateTeam ? (
                        <>
                            <Heading
                                variant="small"
                                title="Dados da Empresa"
                                description="Mantenha as informações do seu negócio atualizadas"
                            />

                            <Form {...update.form()} className="space-y-8">
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Nome fantasia
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                data-test="team-name-input"
                                                defaultValue={team.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="segment">
                                                Segmento
                                            </Label>
                                            <select
                                                id="segment"
                                                name="segment"
                                                defaultValue={
                                                    team.segment ?? ''
                                                }
                                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                                required
                                            >
                                                <option value="">
                                                    Selecione um segmento
                                                </option>
                                                {segments.map((segment) => (
                                                    <option
                                                        key={segment.value}
                                                        value={segment.value}
                                                    >
                                                        {segment.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.segment}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="website">
                                                Site
                                            </Label>
                                            <Input
                                                id="website"
                                                name="website"
                                                type="url"
                                                defaultValue={
                                                    team.website ?? ''
                                                }
                                                placeholder="https://exemplo.com.br"
                                            />
                                            <InputError
                                                message={errors.website}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="business_description">
                                                Descrição do negócio
                                            </Label>
                                            <textarea
                                                id="business_description"
                                                name="business_description"
                                                defaultValue={
                                                    team.businessDescription ??
                                                    ''
                                                }
                                                required
                                                className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                            />
                                            <InputError
                                                message={
                                                    errors.business_description
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="products_services">
                                                Produtos e serviços
                                            </Label>
                                            <textarea
                                                id="products_services"
                                                name="products_services"
                                                defaultValue={
                                                    team.productsServices ?? ''
                                                }
                                                required
                                                className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                            />
                                            <InputError
                                                message={
                                                    errors.products_services
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="target_audience">
                                                Público-alvo
                                            </Label>
                                            <textarea
                                                id="target_audience"
                                                name="target_audience"
                                                defaultValue={
                                                    team.targetAudience ?? ''
                                                }
                                                required
                                                className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                            />
                                            <InputError
                                                message={errors.target_audience}
                                            />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="phone">
                                                    Telefone público
                                                </Label>
                                                <Input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    defaultValue={
                                                        team.phone ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={errors.phone}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="public_email">
                                                    E-mail público
                                                </Label>
                                                <Input
                                                    id="public_email"
                                                    name="public_email"
                                                    type="email"
                                                    defaultValue={
                                                        team.publicEmail ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.public_email
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="address">
                                                Endereço
                                            </Label>
                                            <textarea
                                                id="address"
                                                name="address"
                                                defaultValue={
                                                    team.address ?? ''
                                                }
                                                className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm"
                                            />
                                            <InputError
                                                message={errors.address}
                                            />
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <Button
                                                type="submit"
                                                data-test="team-save-button"
                                                disabled={processing}
                                            >
                                                Salvar alterações
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading
                                variant="small"
                                title="Dados da Empresa"
                                description="Você pode visualizar os dados da Empresa, mas apenas Proprietário e Administrador podem editá-los."
                            />
                            <div className="grid gap-5 rounded-xl border p-5 text-sm">
                                <div>
                                    <p className="text-muted-foreground">
                                        Nome fantasia
                                    </p>
                                    <p className="font-medium">{team.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Segmento
                                    </p>
                                    <p className="font-medium">
                                        {segments.find(
                                            (segment) =>
                                                segment.value === team.segment,
                                        )?.label ?? team.segment}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Site
                                    </p>
                                    <p className="font-medium">
                                        {team.website || 'Não informado'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Descrição do negócio
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {team.businessDescription}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Produtos e serviços
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {team.productsServices}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Público-alvo
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {team.targetAudience}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Telefone público
                                    </p>
                                    <p>{team.phone || 'Não informado'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        E-mail público
                                    </p>
                                    <p>{team.publicEmail || 'Não informado'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">
                                        Endereço
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {team.address || 'Não informado'}
                                    </p>
                                </div>
                            </div>
                        </>
                    )}
                </div>

                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Membros da equipe"
                            description={
                                permissions.canCreateInvitation
                                    ? 'Gerencie quem faz parte desta equipe'
                                    : ''
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
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
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
                                    <div>
                                        <div className="font-medium">
                                            {member.name}
                                        </div>
                                        <div className="text-muted-foreground text-sm">
                                            {member.email}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
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
                </div>

                {invitations.length > 0 ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Convites pendentes"
                            description="Convites que ainda não foram aceitos"
                        />

                        <div className="space-y-3">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.code}
                                    data-test="invitation-row"
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                                            <Mail className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {invitation.role_label}
                                            </div>
                                        </div>
                                    </div>

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
                                                    <p>Cancelar convite</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {canLeave ? (
                    <div className="border-destructive/30 flex items-center justify-between rounded-lg border p-4">
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
                    </div>
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

TeamEdit.layout = () => ({
    breadcrumbs: [
        {
            title: 'Empresa',
            href: edit(),
        },
    ],
});
