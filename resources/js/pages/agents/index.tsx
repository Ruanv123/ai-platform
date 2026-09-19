import { Form, Head, Link, router } from '@inertiajs/react';
import { Bot, MoreHorizontal, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, edit, store, toggle } from '@/routes/agents';
import type { TeamPermissions } from '@/types';

type AgentChannel = {
    id: number;
    type: string;
    typeLabel: string;
    status: string;
    statusLabel: string;
};

type Agent = {
    id: string;
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

function CreateAgentDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form {...store.form()} className="grid gap-5">
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Novo Agente</DialogTitle>
                                <DialogDescription>
                                    Dê um nome e uma descrição para o Agente.
                                    Você configura o restante na tela de
                                    edição.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="new-agent-name">Nome</Label>
                                <Input
                                    id="new-agent-name"
                                    name="name"
                                    placeholder="Ex.: Lia, sua consultora de vendas"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="new-agent-description">
                                    Descrição
                                </Label>
                                <Input
                                    id="new-agent-description"
                                    name="description"
                                    placeholder="Ex.: Consultora de vendas para o site."
                                />
                                <InputError message={errors.description} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Criando...'
                                        : 'Criar Agente'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Agents({ agents, permissions }: Props) {
    const [agentToToggle, setAgentToToggle] = useState<Agent | null>(null);
    const [agentToDelete, setAgentToDelete] = useState<Agent | null>(null);
    const [isCreatingAgent, setIsCreatingAgent] = useState(false);

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
                        <Button onClick={() => setIsCreatingAgent(true)}>
                            <Plus />
                            Novo Agente
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
                                <Button
                                    variant="outline"
                                    onClick={() => setIsCreatingAgent(true)}
                                >
                                    <Plus />
                                    Criar primeiro Agente
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
                                        router.visit(edit(agent.id).url);
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
            <CreateAgentDialog
                open={isCreatingAgent}
                onOpenChange={setIsCreatingAgent}
            />
        </>
    );
}
