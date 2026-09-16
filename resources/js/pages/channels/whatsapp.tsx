import { Form, Head, router } from '@inertiajs/react';
import {
    Check,
    Clipboard,
    ExternalLink,
    MessageCircle,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    destroy,
    test as testConnection,
    update,
    store,
} from '@/routes/channels/whatsapp';
import type { TeamPermissions } from '@/types';

type AgentOption = {
    id: number;
    name: string;
};

type WhatsAppChannel = {
    id: number;
    wabaId: string;
    phoneNumberId: string;
    accessTokenSet: boolean;
    accessTokenMasked: string | null;
    appSecretSet: boolean;
    appSecretMasked: string | null;
    verifyToken: string | null;
    method: string;
    status: 'not_configured' | 'connected' | 'error';
    statusLabel: string;
    lastError: string | null;
    displayPhoneNumber: string | null;
    verifiedName: string | null;
    qualityRating: string | null;
    lastTestedAt: string | null;
    webhookVerifiedAt: string | null;
    agent: AgentOption | null;
};

type Props = {
    whatsapp: WhatsAppChannel | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
    webhookUrl: string;
};

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Ainda não realizado';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusVariant(
    status: WhatsAppChannel['status'],
): 'default' | 'secondary' | 'destructive' | 'outline' {
    return status === 'connected'
        ? 'default'
        : status === 'error'
          ? 'destructive'
          : 'secondary';
}

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? <Check /> : <Clipboard />}
            {copied ? 'Copiado' : label}
        </Button>
    );
}

function WhatsAppForm({
    whatsapp,
    agents,
    canManage,
}: {
    whatsapp: WhatsAppChannel | null;
    agents: AgentOption[];
    canManage: boolean;
}) {
    const [agentId, setAgentId] = useState(
        whatsapp?.agent?.id.toString() ?? 'none',
    );
    const isEditing = whatsapp !== null;

    if (!canManage) {
        return (
            <p className="text-muted-foreground text-sm">
                Apenas Owner e Admin podem alterar a configuração deste canal.
            </p>
        );
    }

    return (
        <Form
            {...(isEditing ? update.form(whatsapp.id) : store.form())}
            className="grid gap-5"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="waba_id">WABA ID</Label>
                            <Input
                                id="waba_id"
                                name="waba_id"
                                defaultValue={whatsapp?.wabaId ?? ''}
                                placeholder="Ex.: 102030405060708"
                            />
                            <InputError message={errors.waba_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="phone_number_id">
                                Phone Number ID
                            </Label>
                            <Input
                                id="phone_number_id"
                                name="phone_number_id"
                                defaultValue={whatsapp?.phoneNumberId ?? ''}
                                placeholder="ID exibido no WhatsApp Manager"
                            />
                            <InputError message={errors.phone_number_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="access_token">
                                Access token permanente
                            </Label>
                            <Input
                                id="access_token"
                                name="access_token"
                                type="password"
                                autoComplete="new-password"
                                placeholder={
                                    whatsapp?.accessTokenMasked ??
                                    'Cole o token permanente'
                                }
                            />
                            {whatsapp?.accessTokenSet && (
                                <p className="text-muted-foreground text-xs">
                                    Deixe vazio para manter o token atual.
                                </p>
                            )}
                            <InputError message={errors.access_token} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="app_secret">App Secret</Label>
                            <Input
                                id="app_secret"
                                name="app_secret"
                                type="password"
                                autoComplete="new-password"
                                placeholder={
                                    whatsapp?.appSecretMasked ??
                                    'Cole o App Secret'
                                }
                            />
                            {whatsapp?.appSecretSet && (
                                <p className="text-muted-foreground text-xs">
                                    Deixe vazio para manter o segredo atual.
                                </p>
                            )}
                            <InputError message={errors.app_secret} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="agent_id">Agente vinculado</Label>
                        <Select value={agentId} onValueChange={setAgentId}>
                            <SelectTrigger id="agent_id">
                                <SelectValue placeholder="Nenhum Agente" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Nenhum Agente
                                </SelectItem>
                                {agents.map((agent) => (
                                    <SelectItem
                                        key={agent.id}
                                        value={agent.id.toString()}
                                    >
                                        {agent.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="agent_id"
                            value={agentId === 'none' ? '' : agentId}
                        />
                        <InputError message={errors.agent_id} />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Salvando...'
                                : isEditing
                                  ? 'Salvar alterações'
                                  : 'Salvar configuração'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function WhatsAppStatus({ whatsapp }: { whatsapp: WhatsAppChannel }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p className="text-muted-foreground text-xs">Status</p>
                <Badge variant={statusVariant(whatsapp.status)}>
                    {whatsapp.statusLabel}
                </Badge>
            </div>
            <div>
                <p className="text-muted-foreground text-xs">Número</p>
                <p className="font-medium">
                    {whatsapp.displayPhoneNumber ?? 'Ainda não testado'}
                </p>
            </div>
            <div>
                <p className="text-muted-foreground text-xs">Nome verificado</p>
                <p className="font-medium">
                    {whatsapp.verifiedName ?? 'Ainda não testado'}
                </p>
            </div>
            <div>
                <p className="text-muted-foreground text-xs">Quality rating</p>
                <p className="font-medium">
                    {whatsapp.qualityRating ?? 'Ainda não testado'}
                </p>
            </div>
            <div>
                <p className="text-muted-foreground text-xs">
                    Agente vinculado
                </p>
                <p className="font-medium">
                    {whatsapp.agent?.name ?? 'Nenhum'}
                </p>
            </div>
        </div>
    );
}

export default function WhatsApp({
    whatsapp,
    agents,
    permissions,
    webhookUrl,
}: Props) {
    const canManage = permissions.canManageChannels;

    const test = () => {
        if (whatsapp !== null) {
            router.post(testConnection(whatsapp.id).url);
        }
    };

    const remove = () => {
        if (
            whatsapp !== null &&
            window.confirm('Remover a configuração deste número do WhatsApp?')
        ) {
            router.delete(destroy(whatsapp.id).url);
        }
    };

    return (
        <>
            <Head title="Canais" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 md:p-6">
                <Heading
                    title="Canais"
                    description="Configure por onde seus Agentes poderão atender."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex items-start gap-3">
                                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                                        <MessageCircle />
                                    </div>
                                    <div>
                                        <CardTitle>
                                            WhatsApp Cloud API
                                        </CardTitle>
                                        <CardDescription>
                                            Conecte o número oficial da sua
                                            Empresa à Meta.
                                        </CardDescription>
                                    </div>
                                </div>
                                {whatsapp !== null && (
                                    <Badge
                                        variant={statusVariant(whatsapp.status)}
                                    >
                                        {whatsapp.statusLabel}
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            {whatsapp !== null && (
                                <>
                                    <WhatsAppStatus whatsapp={whatsapp} />
                                    {whatsapp.lastError && (
                                        <p className="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                                            {whatsapp.lastError}
                                        </p>
                                    )}
                                </>
                            )}

                            <WhatsAppForm
                                whatsapp={whatsapp}
                                agents={agents}
                                canManage={canManage}
                            />

                            {whatsapp !== null && (
                                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                                    <div className="text-muted-foreground text-sm">
                                        Último teste:{' '}
                                        {formatDate(whatsapp.lastTestedAt)}
                                    </div>
                                    {canManage && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={test}
                                            >
                                                <RefreshCw />
                                                Testar conexão
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                onClick={remove}
                                            >
                                                <Trash2 />
                                                Remover número
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Webhook da Meta
                                </CardTitle>
                                <CardDescription>
                                    Use estes dados no painel do aplicativo
                                    Meta.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {whatsapp === null ? (
                                    <p className="text-muted-foreground text-sm">
                                        Salve as credenciais para gerar o verify
                                        token e liberar os dados do webhook.
                                    </p>
                                ) : (
                                    <>
                                        <div className="grid gap-2">
                                            <Label>URL do webhook</Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    value={webhookUrl}
                                                    readOnly
                                                />
                                                <CopyButton
                                                    value={webhookUrl}
                                                    label="Copiar"
                                                />
                                            </div>
                                        </div>
                                        {canManage &&
                                            whatsapp.verifyToken !== null && (
                                                <div className="grid gap-2">
                                                    <Label>Verify token</Label>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            value={
                                                                whatsapp.verifyToken
                                                            }
                                                            readOnly
                                                        />
                                                        <CopyButton
                                                            value={
                                                                whatsapp.verifyToken
                                                            }
                                                            label="Copiar"
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                        <p className="text-muted-foreground text-sm">
                                            Webhook verificado:{' '}
                                            {formatDate(
                                                whatsapp.webhookVerifiedAt,
                                            )}
                                        </p>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="bg-muted/30">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Chat no site
                                </CardTitle>
                                <CardDescription>
                                    Um widget de atendimento para o seu site.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex items-center justify-between gap-3">
                                <Badge variant="secondary">Em breve</Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled
                                >
                                    <ExternalLink />
                                    Conectar
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
