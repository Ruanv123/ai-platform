import { Head, Link, router } from '@inertiajs/react';
import {
    Bot,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create as createAgent,
    destroy,
    edit,
    playground,
    toggle,
} from '@/routes/agents';
import type { TeamPermissions } from '@/types';

type AgentChannel = {
    id: number;
    type: string;
    typeLabel: string;
    status: string;
    statusLabel: string;
};

type Agent = {
    id: number;
    name: string;
    objectiveLabel: string;
    toneLabel: string;
    responseLengthLabel: string;
    isActive: boolean;
    collectionCount: number;
    channelCount: number;
    channels: AgentChannel[];
};

type Props = {
    agents: Agent[];
    permissions: TeamPermissions;
};

function DeleteAgentDialog({
    agent,
    open,
    onOpenChange,
}: {
    agent: Agent | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (agent === null) {
        return null;
    }

    const handleDelete = () => {
        router.delete(destroy(agent.id).url, {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir Agente?</DialogTitle>
                    <DialogDescription>
                        O Agente <strong>{agent.name}</strong> será excluído e
                        não poderá mais ser usado no Playground. Essa ação não
                        pode ser desfeita.
                    </DialogDescription>
                    {agent.channelCount > 0 && (
                        <p className="text-destructive text-sm">
                            Os Canais vinculados continuarão conectados, mas
                            ficarão sem Agente para atender novas conversas.
                        </p>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={handleDelete}>
                        Excluir Agente
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ToggleAgentDialog({
    agent,
    open,
    onOpenChange,
}: {
    agent: Agent | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (agent === null) {
        return null;
    }

    const willActivate = !agent.isActive;
    const handleToggle = () => {
        router.patch(
            toggle(agent.id).url,
            {},
            {
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {willActivate ? 'Ativar Agente?' : 'Desativar Agente?'}
                    </DialogTitle>
                    <DialogDescription>
                        {willActivate
                            ? `O Agente ${agent.name} ficará disponível para os próximos canais.`
                            : `O Agente ${agent.name} deixará de ficar disponível para os próximos canais.`}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button onClick={handleToggle}>
                        {willActivate ? 'Ativar Agente' : 'Desativar Agente'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Agents({ agents, permissions }: Props) {
    const [agentToToggle, setAgentToToggle] = useState<Agent | null>(null);
    const [agentToDelete, setAgentToDelete] = useState<Agent | null>(null);

    return (
        <>
            <Head title="Agentes" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title="Agentes"
                        description="Configure as personas que atenderão seus clientes com o conhecimento da Empresa."
                    />
                    {permissions.canManageAgents && (
                        <Button asChild>
                            <Link href={createAgent.url()}>
                                <Plus />
                                Novo Agente
                            </Link>
                        </Button>
                    )}
                </div>

                {agents.length === 0 ? (
                    <Card className="border-dashed shadow-none">
                        <CardContent className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-xl">
                                <Bot className="text-muted-foreground size-6" />
                            </div>
                            <div className="grid gap-1">
                                <h2 className="font-semibold">
                                    Nenhum Agente cadastrado
                                </h2>
                                <p className="text-muted-foreground max-w-sm text-sm">
                                    Crie a primeira persona para começar a
                                    testar seus atendimentos.
                                </p>
                            </div>
                            {permissions.canManageAgents && (
                                <Button variant="outline" asChild>
                                    <Link href={createAgent.url()}>
                                        <Plus />
                                        Criar primeiro Agente
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="border-border/70 bg-card overflow-hidden rounded-xl border shadow-xs">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="min-w-64">
                                        Agente
                                    </TableHead>
                                    <TableHead>Objetivo</TableHead>
                                    <TableHead>Coleções</TableHead>
                                    <TableHead>Canais vinculados</TableHead>
                                    <TableHead>Status</TableHead>
                                    {permissions.canManageAgents && (
                                        <TableHead className="w-14">
                                            <span className="sr-only">
                                                Ações
                                            </span>
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {agents.map((agent) => {
                                    const activateAgent = () => {
                                        if (permissions.canManageAgents) {
                                            router.visit(edit(agent.id).url);
                                            return;
                                        }

                                        router.visit(playground(agent.id).url);
                                    };

                                    return (
                                        <TableRow
                                            key={agent.id}
                                            className="group cursor-pointer"
                                            tabIndex={0}
                                            onClick={activateAgent}
                                            onKeyDown={(event) => {
                                                if (
                                                    event.key === 'Enter' ||
                                                    event.key === ' '
                                                ) {
                                                    event.preventDefault();
                                                    activateAgent();
                                                }
                                            }}
                                            aria-label={`${permissions.canManageAgents ? 'Editar' : 'Testar'} ${agent.name}`}
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg">
                                                        <Bot className="text-muted-foreground size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {agent.name}
                                                        </p>
                                                        <p className="text-muted-foreground truncate text-xs">
                                                            {agent.toneLabel} ·{' '}
                                                            {
                                                                agent.responseLengthLabel
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {agent.objectiveLabel}
                                            </TableCell>
                                            <TableCell className="font-medium tabular-nums">
                                                {agent.collectionCount}
                                            </TableCell>
                                            <TableCell>
                                                {agent.channelCount === 0 ? (
                                                    <span className="text-muted-foreground">
                                                        Nenhum
                                                    </span>
                                                ) : (
                                                    <div className="flex max-w-xs flex-wrap gap-1.5">
                                                        {agent.channels.map(
                                                            (channel) => (
                                                                <Badge
                                                                    key={
                                                                        channel.id
                                                                    }
                                                                    variant="outline"
                                                                >
                                                                    {
                                                                        channel.typeLabel
                                                                    }
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        agent.isActive
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {agent.isActive
                                                        ? 'Ativo'
                                                        : 'Inativo'}
                                                </Badge>
                                            </TableCell>
                                            {permissions.canManageAgents && (
                                                <TableCell
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Ações para ${agent.name}`}
                                                            >
                                                                <MoreHorizontal />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        playground(
                                                                            agent.id,
                                                                        ).url
                                                                    }
                                                                >
                                                                    <MessageCircle />
                                                                    Testar no
                                                                    Playground
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        edit(
                                                                            agent.id,
                                                                        ).url
                                                                    }
                                                                >
                                                                    <Pencil />
                                                                    Editar
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setAgentToToggle(
                                                                        agent,
                                                                    )
                                                                }
                                                            >
                                                                <Power />
                                                                {agent.isActive
                                                                    ? 'Desativar'
                                                                    : 'Ativar'}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={() =>
                                                                    setAgentToDelete(
                                                                        agent,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                                Excluir
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <ToggleAgentDialog
                agent={agentToToggle}
                open={agentToToggle !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAgentToToggle(null);
                    }
                }}
            />
            <DeleteAgentDialog
                agent={agentToDelete}
                open={agentToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAgentToDelete(null);
                    }
                }}
            />
        </>
    );
}
