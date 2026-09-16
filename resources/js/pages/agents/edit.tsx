import { Form, Head, Link } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Bot, Plus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { useAgentPlaygroundDirty } from '@/hooks/use-agent-playground-dirty';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/agents';

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
    tone: string;
    usesEmojis: boolean;
    responseLength: string;
    welcomeMessage: string;
    instructions: string[];
    rules: string[];
    collectionIds: number[];
};

type Props = {
    agent: Agent | null;
    collections: CollectionOption[];
    objectives: Option[];
    tones: Option[];
    responseLengths: Option[];
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
    collectionIds: number[];
};

type ListEditorProps = {
    label: string;
    description: string;
    placeholder: string;
    name: 'instructions' | 'rules';
    values: string[];
    onChange: (values: string[]) => void;
    errors: Record<string, string>;
    error?: string;
};

function serializeAgentForm(values: AgentFormSnapshot): string {
    return JSON.stringify(values);
}

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
    errors,
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
        <section className="grid gap-3">
            <div className="grid gap-1">
                <Label>{label}</Label>
                <p className="text-muted-foreground text-sm">{description}</p>
            </div>

            <div className="grid gap-2">
                {values.map((value, itemIndex) => {
                    const itemError = errors[`${name}.${itemIndex}`];

                    return (
                        <div
                            className="grid gap-1"
                            key={`${name}-${itemIndex}`}
                        >
                            <div className="flex items-center gap-2">
                                <span className="text-muted-foreground w-6 text-center text-sm tabular-nums">
                                    {itemIndex + 1}
                                </span>
                                <Input
                                    name={`${name}[]`}
                                    value={value}
                                    onChange={(event) =>
                                        updateValue(
                                            itemIndex,
                                            event.target.value,
                                        )
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
                            <InputError className="ml-8" message={itemError} />
                        </div>
                    );
                })}
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
        </section>
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

function AgentForm({
    agent,
    collections,
    objectives,
    tones,
    responseLengths,
}: Props) {
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
    const [selectedCollectionIds, setSelectedCollectionIds] = useState(
        agent?.collectionIds ?? [],
    );
    const [welcomeMessage, setWelcomeMessage] = useState(
        agent?.welcomeMessage ?? '',
    );
    const dirtyStorageKey = agent ? `agent-playground-dirty-${agent.id}` : null;
    const initialFormSnapshot = useMemo(
        () =>
            agent
                ? serializeAgentForm({
                      name: agent.name,
                      objective: agent.objective,
                      tone: agent.tone,
                      responseLength: agent.responseLength,
                      usesEmojis: agent.usesEmojis,
                      welcomeMessage: agent.welcomeMessage,
                      instructions: agent.instructions,
                      rules: agent.rules,
                      collectionIds: agent.collectionIds,
                  })
                : null,
        [agent],
    );
    const currentFormSnapshot = useMemo(
        () =>
            serializeAgentForm({
                name,
                objective,
                tone,
                responseLength,
                usesEmojis,
                welcomeMessage,
                instructions,
                rules,
                collectionIds: selectedCollectionIds,
            }),
        [
            name,
            objective,
            tone,
            responseLength,
            usesEmojis,
            welcomeMessage,
            instructions,
            rules,
            selectedCollectionIds,
        ],
    );
    useAgentPlaygroundDirty(
        agent?.id ?? null,
        initialFormSnapshot !== null &&
            currentFormSnapshot !== initialFormSnapshot,
    );

    const toggleCollection = (collectionId: number) => {
        setSelectedCollectionIds((current) =>
            current.includes(collectionId)
                ? current.filter((id) => id !== collectionId)
                : [...current, collectionId],
        );
    };

    const handleSuccess = () => {
        if (dirtyStorageKey !== null) {
            window.localStorage.removeItem(dirtyStorageKey);
        }
    };

    return (
        <Form
            {...(isEditing ? update.form(agent.id) : store.form())}
            className="grid gap-8"
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
                        errors={errors}
                        error={nestedError(errors, 'instructions')}
                    />
                    <ListEditor
                        label="Regras"
                        description="Defina o que o Agente nunca deve fazer."
                        placeholder="Ex.: Não informe preços sem consultar a base."
                        name="rules"
                        values={rules}
                        onChange={setRules}
                        errors={errors}
                        error={nestedError(errors, 'rules')}
                    />

                    <section className="grid gap-3">
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
                    </section>

                    <div className="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" asChild>
                            <Link href={index.url()}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Salvando...'
                                : isEditing
                                  ? 'Salvar alterações'
                                  : 'Criar Agente'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function AgentEditor(props: Props) {
    const isEditing = props.agent !== null;

    return (
        <>
            <Head title={isEditing ? 'Editar Agente' : 'Novo Agente'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-col gap-4">
                    <Button variant="ghost" size="sm" className="w-fit" asChild>
                        <Link href={index.url()}>
                            <ArrowLeft />
                            Agentes
                        </Link>
                    </Button>
                    <div className="flex items-start gap-3">
                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-xl">
                            <Bot className="text-muted-foreground size-5" />
                        </div>
                        <Heading
                            title={isEditing ? 'Editar Agente' : 'Novo Agente'}
                            description="Configure a persona, o estilo e o conhecimento que o Agente poderá consultar."
                        />
                    </div>
                </div>

                <Card className="w-full max-w-4xl">
                    <CardHeader>
                        <CardTitle>Configuração do Agente</CardTitle>
                        <CardDescription>
                            Essas informações orientam como o Agente conduz cada
                            conversa.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AgentForm key={props.agent?.id ?? 'new'} {...props} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
