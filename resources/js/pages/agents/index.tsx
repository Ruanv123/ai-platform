import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Bot,
    MessageCircle,
    Pencil,
    Plus,
    Power,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { destroy, playground, store, toggle, update } from '@/routes/agents';
import type { TeamPermissions } from '@/types';

type Option = {
    value: string;
    label: string;
};

type CollectionOption = {
    id: number;
    name: string;
};

type Agent = {
    id: number;
    name: string;
    objective: string;
    objectiveLabel: string;
    tone: string;
    toneLabel: string;
    usesEmojis: boolean;
    responseLength: string;
    responseLengthLabel: string;
    welcomeMessage: string;
    instructions: string[];
    rules: string[];
    transferTriggers: string[];
    isActive: boolean;
    collectionIds: number[];
};

type AgentFormSnapshot = {
    name: string;
    objective: string;
    tone: string;
    responseLength: string;
    usesEmojis: boolean;
    welcomeMessage: string;
    instructions: string[];
    rules: string[];
    transferTriggers: string[];
    collectionIds: number[];
};

function serializeAgentForm(values: AgentFormSnapshot): string {
    return JSON.stringify(values);
}

type Props = {
    agents: Agent[];
    collections: CollectionOption[];
    permissions: TeamPermissions;
    objectives: Option[];
    tones: Option[];
    responseLengths: Option[];
};

type AgentFormDialogProps = {
    agent: Agent | null;
    collections: CollectionOption[];
    objectives: Option[];
    tones: Option[];
    responseLengths: Option[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type ListEditorProps = {
    label: string;
    description: string;
    placeholder: string;
    name: 'instructions' | 'rules' | 'transfer_triggers';
    values: string[];
    onChange: (values: string[]) => void;
    error?: string;
};

function nestedError(
    errors: Record<string, string>,
    field: string,
): string | undefined {
    return (
        errors[field] ??
        Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1]
    );
}

function ListEditor({
    label,
    description,
    placeholder,
    name,
    values,
    onChange,
    error,
}: ListEditorProps) {
    const updateValue = (index: number, value: string) => {
        onChange(
            values.map((item, itemIndex) =>
                itemIndex === index ? value : item,
            ),
        );
    };

    const moveValue = (index: number, direction: -1 | 1) => {
        const nextIndex = index + direction;

        if (nextIndex < 0 || nextIndex >= values.length) {
            return;
        }

        const nextValues = [...values];
        [nextValues[index], nextValues[nextIndex]] = [
            nextValues[nextIndex],
            nextValues[index],
        ];
        onChange(nextValues);
    };

    return (
        <div className="grid gap-3">
            <div className="grid gap-1">
                <Label>{label}</Label>
                <p className="text-muted-foreground text-sm">{description}</p>
            </div>

            <div className="grid gap-2">
                {values.map((value, itemIndex) => (
                    <div
                        className="flex items-center gap-2"
                        key={`${name}-${itemIndex}`}
                    >
                        <span className="text-muted-foreground w-6 text-center text-sm tabular-nums">
                            {itemIndex + 1}
                        </span>
                        <Input
                            name={`${name}[]`}
                            value={value}
                            onChange={(event) =>
                                updateValue(itemIndex, event.target.value)
                            }
                            placeholder={placeholder}
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => moveValue(itemIndex, -1)}
                            disabled={itemIndex === 0}
                            aria-label={`Mover item ${itemIndex + 1} para cima`}
                        >
                            <ArrowUp />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => moveValue(itemIndex, 1)}
                            disabled={itemIndex === values.length - 1}
                            aria-label={`Mover item ${itemIndex + 1} para baixo`}
                        >
                            <ArrowDown />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive"
                            onClick={() =>
                                onChange(
                                    values.filter(
                                        (_, currentIndex) =>
                                            currentIndex !== itemIndex,
                                    ),
                                )
                            }
                            aria-label={`Remover item ${itemIndex + 1}`}
                        >
                            <X />
                        </Button>
                    </div>
                ))}
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit"
                onClick={() => onChange([...values, ''])}
            >
                <Plus />
                Adicionar item
            </Button>
            <InputError message={error} />
        </div>
    );
}

function AgentFormFields({
    agent,
    collections,
    objectives,
    tones,
    responseLengths,
    onSuccess,
}: Omit<AgentFormDialogProps, 'open' | 'onOpenChange'> & {
    onSuccess: () => void;
}) {
    const isEditing = agent !== null;
    const [name, setName] = useState(agent?.name ?? '');
    const [objective, setObjective] = useState(
        agent?.objective ?? objectives[0]?.value ?? '',
    );
    const [tone, setTone] = useState(agent?.tone ?? tones[0]?.value ?? '');
    const [responseLength, setResponseLength] = useState(
        agent?.responseLength ?? responseLengths[0]?.value ?? '',
    );
    const [usesEmojis, setUsesEmojis] = useState(agent?.usesEmojis ?? false);
    const [instructions, setInstructions] = useState(agent?.instructions ?? []);
    const [rules, setRules] = useState(agent?.rules ?? []);
    const [transferTriggers, setTransferTriggers] = useState(
        agent?.transferTriggers ?? [],
    );
    const [selectedCollectionIds, setSelectedCollectionIds] = useState(
        agent?.collectionIds ?? [],
    );
    const [welcomeMessage, setWelcomeMessage] = useState(
        agent?.welcomeMessage ?? '',
    );
    const dirtyStorageKey = agent ? `agent-playground-dirty-${agent.id}` : null;
    const initialFormSnapshot = agent
        ? serializeAgentForm({
              name: agent.name,
              objective: agent.objective,
              tone: agent.tone,
              responseLength: agent.responseLength,
              usesEmojis: agent.usesEmojis,
              welcomeMessage: agent.welcomeMessage,
              instructions: agent.instructions,
              rules: agent.rules,
              transferTriggers: agent.transferTriggers,
              collectionIds: agent.collectionIds,
          })
        : null;

    useEffect(() => {
        if (dirtyStorageKey === null || initialFormSnapshot === null) {
            return;
        }

        const currentFormSnapshot = serializeAgentForm({
            name,
            objective,
            tone,
            responseLength,
            usesEmojis,
            welcomeMessage,
            instructions,
            rules,
            transferTriggers,
            collectionIds: selectedCollectionIds,
        });

        window.localStorage.setItem(
            dirtyStorageKey,
            String(currentFormSnapshot !== initialFormSnapshot),
        );
    }, [
        dirtyStorageKey,
        initialFormSnapshot,
        name,
        objective,
        tone,
        responseLength,
        usesEmojis,
        welcomeMessage,
        instructions,
        rules,
        transferTriggers,
        selectedCollectionIds,
    ]);

    const handleSuccess = () => {
        if (dirtyStorageKey !== null) {
            window.localStorage.removeItem(dirtyStorageKey);
        }

        onSuccess();
    };

    const toggleCollection = (collectionId: number) => {
        setSelectedCollectionIds((current) =>
            current.includes(collectionId)
                ? current.filter((id) => id !== collectionId)
                : [...current, collectionId],
        );
    };

    return (
        <Form
            {...(isEditing ? update.form(agent.id) : store.form())}
            className="grid max-h-[75vh] gap-6 overflow-y-auto pr-1"
            onSuccess={handleSuccess}
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="objective" value={objective} />
                    <input type="hidden" name="tone" value={tone} />
                    <input
                        type="hidden"
                        name="response_length"
                        value={responseLength}
                    />
                    <input
                        type="hidden"
                        name="uses_emojis"
                        value={usesEmojis ? '1' : '0'}
                    />
                    {selectedCollectionIds.map((collectionId) => (
                        <input
                            key={collectionId}
                            type="hidden"
                            name="collection_ids[]"
                            value={collectionId}
                        />
                    ))}

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="agent-name">Nome da persona</Label>
                            <Input
                                id="agent-name"
                                name="name"
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                placeholder="Ex.: Lia, sua consultora de vendas"
                                autoFocus
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <SelectField
                            id="agent-objective"
                            label="Objetivo"
                            value={objective}
                            onValueChange={setObjective}
                            options={objectives}
                            error={errors.objective}
                        />
                        <SelectField
                            id="agent-tone"
                            label="Tom de voz"
                            value={tone}
                            onValueChange={setTone}
                            options={tones}
                            error={errors.tone}
                        />
                        <SelectField
                            id="agent-response-length"
                            label="Tamanho das respostas"
                            value={responseLength}
                            onValueChange={setResponseLength}
                            options={responseLengths}
                            error={errors.response_length}
                        />

                        <div className="grid gap-2">
                            <Label>Emojis</Label>
                            <Button
                                type="button"
                                variant={usesEmojis ? 'default' : 'outline'}
                                className="justify-start"
                                onClick={() =>
                                    setUsesEmojis((current) => !current)
                                }
                            >
                                {usesEmojis
                                    ? 'O Agente pode usar emojis'
                                    : 'Sem emojis'}
                            </Button>
                            <InputError message={errors.uses_emojis} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="agent-welcome-message">
                                Mensagem de boas-vindas
                            </Label>
                            <textarea
                                id="agent-welcome-message"
                                name="welcome_message"
                                value={welcomeMessage}
                                onChange={(event) =>
                                    setWelcomeMessage(event.target.value)
                                }
                                placeholder="Ex.: Olá! Sou a Lia. Como posso ajudar?"
                                className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                required
                            />
                            <InputError message={errors.welcome_message} />
                        </div>
                    </div>

                    <ListEditor
                        label="Instruções"
                        description="Oriente o Agente sobre como conduzir cada conversa."
                        placeholder="Ex.: Faça uma pergunta por vez."
                        name="instructions"
                        values={instructions}
                        onChange={setInstructions}
                        error={nestedError(errors, 'instructions')}
                    />
                    <ListEditor
                        label="Regras"
                        description="Defina o que o Agente nunca deve fazer."
                        placeholder="Ex.: Não informe preços sem consultar a base."
                        name="rules"
                        values={rules}
                        onChange={setRules}
                        error={nestedError(errors, 'rules')}
                    />
                    <ListEditor
                        label="Gatilhos de transferência"
                        description="Registre situações que deverão ser transferidas para uma pessoa."
                        placeholder="Ex.: O cliente pedir para falar com um atendente."
                        name="transfer_triggers"
                        values={transferTriggers}
                        onChange={setTransferTriggers}
                        error={nestedError(errors, 'transfer_triggers')}
                    />

                    <div className="grid gap-3">
                        <div className="grid gap-1">
                            <Label>Coleções consultadas</Label>
                            <p className="text-muted-foreground text-sm">
                                O Agente poderá consultar apenas o conhecimento
                                selecionado.
                            </p>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {collections.map((collection) => {
                                const selected = selectedCollectionIds.includes(
                                    collection.id,
                                );

                                return (
                                    <label
                                        key={collection.id}
                                        className="border-input hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selected}
                                            onChange={() =>
                                                toggleCollection(collection.id)
                                            }
                                            className="accent-primary size-4"
                                        />
                                        <span>{collection.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                        {collections.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                Crie uma Coleção na Base de conhecimento para
                                disponibilizar conteúdo ao Agente.
                            </p>
                        )}
                        <InputError
                            message={nestedError(errors, 'collection_ids')}
                        />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Salvando...'
                                : isEditing
                                  ? 'Salvar alterações'
                                  : 'Criar Agente'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

function SelectField({
    id,
    label,
    value,
    onValueChange,
    options,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options: Option[];
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue
                        placeholder={`Selecione ${label.toLowerCase()}`}
                    />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

function AgentFormDialog({
    agent,
    collections,
    objectives,
    tones,
    responseLengths,
    open,
    onOpenChange,
}: AgentFormDialogProps) {
    const isEditing = agent !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Editar Agente' : 'Novo Agente'}
                    </DialogTitle>
                    <DialogDescription>
                        Configure a persona, o estilo e o conhecimento que o
                        Agente poderá consultar.
                    </DialogDescription>
                </DialogHeader>
                <AgentFormFields
                    key={agent?.id ?? 'new'}
                    agent={agent}
                    collections={collections}
                    objectives={objectives}
                    tones={tones}
                    responseLengths={responseLengths}
                    onSuccess={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

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

export default function Agents({
    agents,
    collections,
    permissions,
    objectives,
    tones,
    responseLengths,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [agentToEdit, setAgentToEdit] = useState<Agent | null>(null);
    const [agentToToggle, setAgentToToggle] = useState<Agent | null>(null);
    const [agentToDelete, setAgentToDelete] = useState<Agent | null>(null);

    const openCreateDialog = () => {
        setAgentToEdit(null);
        setFormOpen(true);
    };

    const openEditDialog = (agent: Agent) => {
        setAgentToEdit(agent);
        setFormOpen(true);
    };

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
                        <Button onClick={openCreateDialog}>
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
                                    onClick={openCreateDialog}
                                >
                                    <Plus />
                                    Criar primeiro Agente
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {agents.map((agent) => (
                            <Card
                                key={agent.id}
                                className="group relative overflow-hidden shadow-none transition-shadow hover:shadow-sm"
                            >
                                <CardHeader className="gap-3">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg">
                                            <Bot className="text-muted-foreground size-5" />
                                        </div>
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
                                    </div>
                                    <div className="grid gap-1">
                                        <CardTitle className="text-base">
                                            {agent.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {agent.objectiveLabel}
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between gap-3">
                                    <div className="text-muted-foreground grid gap-1 text-sm">
                                        <span>{agent.toneLabel}</span>
                                        <span>
                                            {agent.responseLengthLabel} ·{' '}
                                            {agent.collectionIds.length}{' '}
                                            {agent.collectionIds.length === 1
                                                ? 'Coleção'
                                                : 'Coleções'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={playground(agent.id).url}
                                            >
                                                <MessageCircle />
                                                Testar
                                            </Link>
                                        </Button>
                                        {permissions.canManageAgents && (
                                            <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setAgentToToggle(agent)
                                                    }
                                                    aria-label={`${agent.isActive ? 'Desativar' : 'Ativar'} ${agent.name}`}
                                                >
                                                    <Power />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditDialog(agent)
                                                    }
                                                    aria-label={`Editar ${agent.name}`}
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setAgentToDelete(agent)
                                                    }
                                                    aria-label={`Excluir ${agent.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <AgentFormDialog
                agent={agentToEdit}
                collections={collections}
                objectives={objectives}
                tones={tones}
                responseLengths={responseLengths}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
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
