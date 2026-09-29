import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Bot, Plus, X } from 'lucide-react';
import { useState } from 'react';
import AgentPlayground, {
    type AgentDraft,
} from '@/components/agent-playground';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { index, publish, update } from '@/routes/agents';
import { show as showIntegration } from '@/routes/integrations';

type Option = {
    value: string;
    label: string;
};

type CollectionOption = {
    id: string;
    name: string;
};

type SchedulableCalendarOption = {
    id: string;
    name: string;
    description: string | null;
};

type Agent = AgentDraft & {
    id: string;
    description: string | null;
    crmLookupConfigured: boolean;
};

type Props = {
    agent: Agent;
    collections: CollectionOption[];
    schedulableCalendars: SchedulableCalendarOption[];
    agendaConnected: boolean;
    crmConnected: boolean;
    shopConnected: boolean;
    productSearchConfigured: boolean;
    purchaseLinkConfigured: boolean;
    publishedVersion: {
        number: number;
    } | null;
    objectives: Option[];
    tones: Option[];
    responseLengths: Option[];
    permissions: {
        canManageAgents: boolean;
    };
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
    permissions,
    schedulableCalendars,
    agendaConnected,
    crmConnected,
    shopConnected,
    productSearchConfigured,
    purchaseLinkConfigured,
    publishedVersion,
}: Props) {
    const [name, setName] = useState(agent.name);
    const [description, setDescription] = useState(agent.description ?? '');
    const [objective, setObjective] = useState(
        agent.objective || objectives[0]?.value || '',
    );
    const [searchProducts, setSearchProducts] = useState(agent.searchProducts);
    const [isProductSearchConfigured, setIsProductSearchConfigured] = useState(
        productSearchConfigured,
    );
    const [sendPurchaseLink, setSendPurchaseLink] = useState(
        agent.sendPurchaseLink,
    );
    const [isPurchaseLinkConfigured, setIsPurchaseLinkConfigured] = useState(
        purchaseLinkConfigured,
    );
    const [tone, setTone] = useState(agent.tone || tones[0]?.value || '');
    const [responseLength, setResponseLength] = useState(
        agent.responseLength || responseLengths[0]?.value || '',
    );
    const [usesEmojis, setUsesEmojis] = useState(agent.usesEmojis);
    const [instructions, setInstructions] = useState(agent.instructions);
    const [rules, setRules] = useState(agent.rules);
    const [selectedCollectionIds, setSelectedCollectionIds] = useState(
        agent.collectionIds,
    );
    const [selectedSchedulableCalendarIds, setSelectedSchedulableCalendarIds] =
        useState(agent.schedulableCalendarIds);
    const [crmLookupEnabled, setCrmLookupEnabled] = useState(
        agent.crmLookupEnabled,
    );
    const [crmLookupConfigured, setCrmLookupConfigured] = useState(
        agent.crmLookupConfigured,
    );
    const [welcomeMessage, setWelcomeMessage] = useState(agent.welcomeMessage);
    const [activePanel, setActivePanel] = useState<'config' | 'playground'>(
        'config',
    );
    const [isPublishing, setIsPublishing] = useState(false);
    const draft: AgentDraft = {
        name,
        objective,
        tone,
        usesEmojis,
        responseLength,
        welcomeMessage,
        instructions,
        rules,
        collectionIds: selectedCollectionIds,
        schedulableCalendarIds: selectedSchedulableCalendarIds,
        crmLookupEnabled,
        searchProducts,
        sendPurchaseLink,
    };

    const updateObjective = (nextObjective: string) => {
        setObjective(nextObjective);

        if (!isProductSearchConfigured) {
            setSearchProducts(nextObjective === 'sales' && shopConnected);
        }

        if (!isPurchaseLinkConfigured) {
            setSendPurchaseLink(nextObjective === 'sales' && shopConnected);
        }

        if (!crmLookupConfigured) {
            setCrmLookupEnabled(
                crmConnected &&
                    ['sales', 'qualification'].includes(nextObjective),
            );
        }
    };

    const publishDraft = () => {
        router.post(
            publish.url(agent.id),
            {
                name,
                description: description || null,
                objective,
                ...(isProductSearchConfigured
                    ? { search_products: searchProducts }
                    : {}),
                ...(isPurchaseLinkConfigured
                    ? { send_purchase_link: sendPurchaseLink }
                    : {}),
                tone,
                uses_emojis: usesEmojis,
                response_length: responseLength,
                welcome_message: welcomeMessage,
                instructions,
                rules,
                collection_ids: selectedCollectionIds,
                schedulable_calendar_ids: selectedSchedulableCalendarIds,
                ...(crmConnected ? { crm_lookup_enabled: crmLookupEnabled } : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setIsPublishing(true),
                onFinish: () => setIsPublishing(false),
            },
        );
    };

    const toggleCollection = (collectionId: string) => {
        setSelectedCollectionIds((current) =>
            current.includes(collectionId)
                ? current.filter((id) => id !== collectionId)
                : [...current, collectionId],
        );
    };

    const toggleSchedulableCalendar = (calendarId: string) => {
        setSelectedSchedulableCalendarIds((current) =>
            current.includes(calendarId)
                ? current.filter((id) => id !== calendarId)
                : [...current, calendarId],
        );
    };

    return (
        <Form {...update.form(agent.id)} className="grid gap-5">
            {({ errors, processing }) => (
                <>
                    <div className="bg-muted grid grid-cols-2 rounded-lg p-1 lg:hidden">
                        <Button
                            type="button"
                            variant={
                                activePanel === 'config' ? 'secondary' : 'ghost'
                            }
                            onClick={() => setActivePanel('config')}
                        >
                            Configuração
                        </Button>
                        <Button
                            type="button"
                            variant={
                                activePanel === 'playground'
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            onClick={() => setActivePanel('playground')}
                        >
                            Playground
                        </Button>
                    </div>

                    <div className="grid min-h-0 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.85fr)]">
                        <Card
                            className={`${activePanel === 'config' ? '' : 'hidden'} min-w-0 shadow-none lg:block`}
                        >
                            <CardHeader>
                                <CardTitle>Configuração do Agente</CardTitle>
                                <CardDescription>
                                    Essas informações orientam como o Agente
                                    conduz cada conversa.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <fieldset
                                    disabled={!permissions.canManageAgents}
                                    className="grid gap-8"
                                >
                                    <input
                                        type="hidden"
                                        name="objective"
                                        value={objective}
                                    />
                                    {shopConnected &&
                                        isProductSearchConfigured && (
                                            <input
                                                type="hidden"
                                                name="search_products"
                                                value={
                                                    searchProducts ? '1' : '0'
                                                }
                                            />
                                        )}
                                    {shopConnected &&
                                        isPurchaseLinkConfigured && (
                                            <input
                                                type="hidden"
                                                name="send_purchase_link"
                                                value={
                                                    sendPurchaseLink ? '1' : '0'
                                                }
                                            />
                                        )}
                                    <input
                                        type="hidden"
                                        name="tone"
                                        value={tone}
                                    />
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
                                    {crmConnected && (
                                        <input
                                            type="hidden"
                                            name="crm_lookup_enabled"
                                            value={crmLookupEnabled ? '1' : '0'}
                                        />
                                    )}
                                    {selectedCollectionIds.map(
                                        (collectionId) => (
                                            <input
                                                key={collectionId}
                                                type="hidden"
                                                name="collection_ids[]"
                                                value={collectionId}
                                            />
                                        ),
                                    )}
                                    {selectedSchedulableCalendarIds.map(
                                        (calendarId) => (
                                            <input
                                                key={calendarId}
                                                type="hidden"
                                                name="schedulable_calendar_ids[]"
                                                value={calendarId}
                                            />
                                        ),
                                    )}

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="agent-name">
                                                Nome da persona
                                            </Label>
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

                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="agent-description">
                                                Descrição
                                            </Label>
                                            <Input
                                                id="agent-description"
                                                name="description"
                                                value={description}
                                                onChange={(event) =>
                                                    setDescription(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Ex.: Consultora de vendas para o site."
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>

                                        <SelectField
                                            id="agent-objective"
                                            label="Objetivo"
                                            value={objective}
                                            onValueChange={updateObjective}
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
                                                variant={
                                                    usesEmojis
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="justify-start"
                                                onClick={() =>
                                                    setUsesEmojis(
                                                        (current) => !current,
                                                    )
                                                }
                                            >
                                                {usesEmojis
                                                    ? 'O Agente pode usar emojis'
                                                    : 'Sem emojis'}
                                            </Button>
                                            <InputError
                                                message={errors.uses_emojis}
                                            />
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
                                                    setWelcomeMessage(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Ex.: Olá! Sou a Lia. Como posso ajudar?"
                                                className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                                required
                                            />
                                            <InputError
                                                message={errors.welcome_message}
                                            />
                                        </div>
                                    </div>

                                    {shopConnected && (
                                        <section className="grid gap-3">
                                            <div className="grid gap-1">
                                                <Label>Buscar produtos</Label>
                                                <p className="text-muted-foreground text-sm">
                                                    Permita que o Agente
                                                    consulte o catálogo da Loja
                                                    para recomendar produtos com
                                                    preços, opções e
                                                    disponibilidade atuais.
                                                </p>
                                            </div>
                                            <label className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={searchProducts}
                                                    onChange={(event) => {
                                                        setSearchProducts(
                                                            event.target
                                                                .checked,
                                                        );
                                                        setIsProductSearchConfigured(
                                                            true,
                                                        );
                                                    }}
                                                    className="accent-primary mt-0.5 size-4"
                                                />
                                                <span>
                                                    O Agente pode buscar
                                                    produtos da Loja
                                                </span>
                                            </label>
                                            <div className="grid gap-1 pt-2">
                                                <Label>
                                                    Mandar Link de compra
                                                </Label>
                                                <p className="text-muted-foreground text-sm">
                                                    Permita que o Agente gere um
                                                    link de checkout com as
                                                    Variantes e quantidades
                                                    escolhidas pelo Contato.
                                                </p>
                                            </div>
                                            <label className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={sendPurchaseLink}
                                                    onChange={(event) => {
                                                        setSendPurchaseLink(
                                                            event.target
                                                                .checked,
                                                        );
                                                        setIsPurchaseLinkConfigured(
                                                            true,
                                                        );
                                                    }}
                                                    className="accent-primary mt-0.5 size-4"
                                                />
                                                <span>
                                                    O Agente pode mandar Links
                                                    de compra da Loja
                                                </span>
                                            </label>
                                        </section>
                                    )}

                                    <ListEditor
                                        label="Instruções"
                                        description="Oriente o Agente sobre como conduzir cada conversa."
                                        placeholder="Ex.: Faça uma pergunta por vez."
                                        name="instructions"
                                        values={instructions}
                                        onChange={setInstructions}
                                        errors={errors}
                                        error={nestedError(
                                            errors,
                                            'instructions',
                                        )}
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
                                                O Agente poderá consultar apenas
                                                o conhecimento selecionado.
                                            </p>
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {collections.map((collection) => {
                                                const selected =
                                                    selectedCollectionIds.includes(
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
                                                                toggleCollection(
                                                                    collection.id,
                                                                )
                                                            }
                                                            className="accent-primary size-4"
                                                        />
                                                        <span>
                                                            {collection.name}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        {collections.length === 0 && (
                                            <p className="text-muted-foreground text-sm">
                                                Crie uma Coleção na Base de
                                                conhecimento para disponibilizar
                                                conteúdo ao Agente.
                                            </p>
                                        )}
                                        <InputError
                                            message={nestedError(
                                                errors,
                                                'collection_ids',
                                            )}
                                        />
                                    </section>

                                    <section className="grid gap-3">
                                        <div className="grid gap-1">
                                            <Label>Agenda</Label>
                                            <p className="text-muted-foreground text-sm">
                                                Escolha em quais Calendários
                                                agendáveis este Agente pode
                                                marcar.
                                            </p>
                                        </div>
                                        {!agendaConnected ? (
                                            <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                                                Conecte a Agenda em{' '}
                                                <Link
                                                    className="text-primary underline"
                                                    href={
                                                        showIntegration(
                                                            'agenda',
                                                        ).url
                                                    }
                                                >
                                                    Integrações
                                                </Link>{' '}
                                                para liberar Calendários.
                                            </p>
                                        ) : schedulableCalendars.length ===
                                          0 ? (
                                            <p className="text-muted-foreground rounded-md border border-dashed p-3 text-sm">
                                                Libere um Calendário na Agenda
                                                para disponibilizá-lo aqui.
                                            </p>
                                        ) : (
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {schedulableCalendars.map(
                                                    (calendar) => {
                                                        const selected =
                                                            selectedSchedulableCalendarIds.includes(
                                                                calendar.id,
                                                            );

                                                        return (
                                                            <label
                                                                key={
                                                                    calendar.id
                                                                }
                                                                className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        selected
                                                                    }
                                                                    onChange={() =>
                                                                        toggleSchedulableCalendar(
                                                                            calendar.id,
                                                                        )
                                                                    }
                                                                    className="accent-primary mt-0.5 size-4"
                                                                />
                                                                <span>
                                                                    <span className="block font-medium">
                                                                        {
                                                                            calendar.name
                                                                        }
                                                                    </span>
                                                                    {calendar.description && (
                                                                        <span className="text-muted-foreground block text-xs">
                                                                            {
                                                                                calendar.description
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </label>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        )}
                                        <InputError
                                            message={nestedError(
                                                errors,
                                                'schedulable_calendar_ids',
                                            )}
                                        />
                                    </section>

                                    {crmConnected && (
                                        <section className="grid gap-3">
                                            <div className="grid gap-1">
                                                <Label>RD Station CRM</Label>
                                                <p className="text-muted-foreground text-sm">
                                                    Permita que este Agente consulte a ficha comercial do Contato durante o atendimento.
                                                </p>
                                            </div>
                                            <label className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={crmLookupEnabled}
                                                onChange={(event) => {
                                                    setCrmLookupConfigured(true);
                                                    setCrmLookupEnabled(event.target.checked)
                                                }}
                                                    className="accent-primary mt-0.5 size-4"
                                                />
                                                <span>Consultar ficha no CRM</span>
                                            </label>
                                        </section>
                                    )}

                                    {permissions.canManageAgents && (
                                        <div className="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:justify-end">
                                            <p className="text-muted-foreground mr-auto self-center text-sm">
                                                {publishedVersion === null
                                                    ? 'Ainda não há uma versão publicada nos Canais.'
                                                    : `Última versão publicada nos Canais: ${publishedVersion.number}.`}{' '}
                                                O rascunho atual só vai aos
                                                Canais quando você publicar.
                                            </p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link href={index.url()}>
                                                    Cancelar
                                                </Link>
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Salvando rascunho...'
                                                    : 'Salvar rascunho'}
                                            </Button>
                                            <Button
                                                type="button"
                                                onClick={publishDraft}
                                                disabled={
                                                    processing || isPublishing
                                                }
                                            >
                                                {isPublishing
                                                    ? 'Publicando...'
                                                    : 'Publicar para os Canais'}
                                            </Button>
                                        </div>
                                    )}
                                </fieldset>
                            </CardContent>
                        </Card>
                        <div
                            className={`${activePanel === 'playground' ? '' : 'hidden'} min-w-0 lg:block`}
                        >
                            <AgentPlayground draft={draft} />
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function AgentEditor(props: Props) {
    return (
        <>
            <Head title="Editar Agente" />

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
                            title="Editar Agente"
                            description={
                                props.permissions.canManageAgents
                                    ? 'Configure a persona, o estilo e o conhecimento que o Agente poderá consultar.'
                                    : 'Veja a configuração e teste o Agente sem alterar os dados da Empresa.'
                            }
                        />
                    </div>
                </div>

                <AgentForm key={props.agent.id} {...props} />
            </div>
        </>
    );
}
