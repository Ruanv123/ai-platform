import { Form, Head, Link, router } from '@inertiajs/react';
import { FlaskConical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    ChannelSignalLamp,
    channelSignal,
    formatChannelMoment,
    type ChannelReadiness,
} from '@/components/channel-readiness';
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
import { index as channelsIndex } from '@/routes/channels';
import { destroy, store, update } from '@/routes/channels/whatsapp-test';
import type { TeamPermissions } from '@/types';

type AgentOption = {
    id: string;
    name: string;
};

type WhatsAppTestChannel = ChannelReadiness & {
    id: number;
    serviceUrl: string;
    instanceName: string;
    accessTokenSet: boolean;
    accessTokenMasked: string | null;
};

type Props = {
    whatsappTest: WhatsAppTestChannel | null;
    agents: AgentOption[];
    permissions: TeamPermissions;
};

const emptyReadiness: ChannelReadiness = {
    status: 'not_configured',
    statusLabel: 'Não conectado',
    lastError: null,
    credentialsSavedAt: null,
    webhookVerifiedAt: null,
    lastTestedAt: null,
    agent: null,
};

function WhatsAppTestForm({
    whatsappTest,
    agents,
    canManage,
}: {
    whatsappTest: WhatsAppTestChannel | null;
    agents: AgentOption[];
    canManage: boolean;
}) {
    const [agentId, setAgentId] = useState(
        whatsappTest?.agent?.id.toString() ?? 'none',
    );
    const isEditing = whatsappTest !== null;

    if (!canManage) {
        return (
            <p className="text-muted-foreground text-sm">
                Somente Owner e Admin alteram este Canal.
            </p>
        );
    }

    return (
        <Form
            {...(isEditing ? update.form(whatsappTest.id) : store.form())}
            className="grid gap-5"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="service_url">
                                Endereço do serviço
                            </Label>
                            <Input
                                id="service_url"
                                name="service_url"
                                type="url"
                                defaultValue={whatsappTest?.serviceUrl ?? ''}
                                placeholder="Ex.: http://localhost:8080"
                            />
                            <InputError message={errors.service_url} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="instance_name">
                                Nome da instância
                            </Label>
                            <Input
                                id="instance_name"
                                name="instance_name"
                                defaultValue={whatsappTest?.instanceName ?? ''}
                                placeholder="Ex.: desenvolvimento"
                            />
                            <InputError message={errors.instance_name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="access_token">
                            Credencial de acesso
                        </Label>
                        <Input
                            id="access_token"
                            name="access_token"
                            type="password"
                            autoComplete="new-password"
                            placeholder={
                                whatsappTest?.accessTokenMasked ??
                                'Cole a credencial do serviço'
                            }
                        />
                        {whatsappTest?.accessTokenSet && (
                            <p className="text-muted-foreground text-xs">
                                Deixe vazio para manter a credencial atual.
                            </p>
                        )}
                        <InputError message={errors.access_token} />
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
                                  : 'Salvar conexão'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export default function WhatsAppTest({
    whatsappTest,
    agents,
    permissions,
}: Props) {
    const canManage = permissions.canManageChannels;
    const readiness = whatsappTest ?? emptyReadiness;
    const signal = channelSignal(readiness);

    const remove = () => {
        if (
            whatsappTest !== null &&
            window.confirm('Remover a configuração do WhatsApp de testes?')
        ) {
            router.delete(destroy(whatsappTest.id).url);
        }
    };

    return (
        <>
            <Head title="WhatsApp de testes" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <Link
                    href={channelsIndex()}
                    className="text-muted-foreground hover:text-foreground w-fit text-sm font-medium transition-colors"
                >
                    ← Voltar para Canais
                </Link>
                <Heading
                    title="WhatsApp de testes"
                    description="Conecte um número por um serviço não oficial para desenvolver o atendimento."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]">
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="grid gap-1">
                                    <CardTitle className="text-base">
                                        Conexão do serviço
                                    </CardTitle>
                                    <CardDescription>
                                        A configuração fica disponível apenas
                                        para o ambiente local e administradores
                                        da plataforma.
                                    </CardDescription>
                                </div>
                                <FlaskConical className="text-muted-foreground size-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <WhatsAppTestForm
                                whatsappTest={whatsappTest}
                                agents={agents}
                                canManage={canManage}
                            />
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Estado do Canal
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <ChannelSignalLamp signal={signal} />
                                <p className="text-muted-foreground text-sm">
                                    {whatsappTest === null
                                        ? 'Salve a conexão para habilitar o recebimento de eventos.'
                                        : 'O Canal está pronto para o fluxo de atendimento no serviço de testes.'}
                                </p>
                                {whatsappTest?.credentialsSavedAt !== null &&
                                    whatsappTest?.credentialsSavedAt !==
                                        undefined && (
                                        <p className="text-muted-foreground text-xs">
                                            Salvo em{' '}
                                            {formatChannelMoment(
                                                whatsappTest.credentialsSavedAt,
                                            )}
                                        </p>
                                    )}
                                {canManage && whatsappTest !== null && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive hover:text-destructive justify-start px-0"
                                        onClick={remove}
                                    >
                                        <Trash2 />
                                        Remover configuração
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Escopo desta tela
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-muted-foreground text-sm leading-relaxed">
                                    Esta etapa salva a conexão mínima para o
                                    desenvolvimento. Pareamento, QR code e ciclo
                                    de vida da instância ficam fora desta tela.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
