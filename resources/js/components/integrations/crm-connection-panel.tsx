import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import Heading from '@/components/heading';
import { destroy, index } from '@/routes/integrations';
import visibleToAgent from '@/routes/integrations/crm/fields/visible-to-agent';
import { connect } from '@/routes/integrations/crm/rd-station';
import type { TeamPermissions } from '@/types';
import defaultOwner from '@/routes/integrations/crm/default-owner';
import commercialStages from '@/routes/integrations/crm/commercial-stages';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Integration = {
    id: string;
    name: string;
    status: string;
    statusLabel: string;
    statusMessage: string | null;
    accountEmail?: string | null;
    crmAccount?: { teams: { id: string; name: string }[] } | null;
    crmMetadataLoadedAt?: string | null;
};

type Props = {
    integration: Integration | null;
    crmError: string | null;
    crmSuccess: string | null;
    crmFields: { id: string; name: string }[];
    crmOwners: { id: string; name: string }[];
    crmPipelines: { id: string; name: string; stages: { id: string; name: string }[] }[];
    commercialCrmStageIds: string[];
    defaultCrmOwnerId: string | null;
    visibleCrmFieldIds: string[];
    permissions: TeamPermissions;
};

export default function CrmConnectionPanel({
    integration,
    crmError,
    crmSuccess,
    crmFields,
    crmOwners,
    crmPipelines,
    commercialCrmStageIds,
    defaultCrmOwnerId,
    visibleCrmFieldIds,
    permissions,
}: Props) {
    const [selectedFieldIds, setSelectedFieldIds] = useState(visibleCrmFieldIds);
    const [selectedDefaultOwnerId, setSelectedDefaultOwnerId] = useState(
        defaultCrmOwnerId ?? 'none',
    );
    const [selectedCommercialStageIds, setSelectedCommercialStageIds] = useState(
        commercialCrmStageIds,
    );
    const connected = integration?.status === 'connected';
    const teamAccountNames =
        integration?.crmAccount?.teams.map(
            (team) => `${team.name} (ID ${team.id})`,
        ) ?? [];

    return (
        <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <div className="flex items-start gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    aria-label="Voltar para integrações"
                >
                    <Link href={index()}>
                        <ArrowLeft />
                    </Link>
                </Button>
                <Heading
                    title="RD Station CRM"
                    description="Conecte a conta da Empresa pela autorização oficial do RD Station CRM."
                />
            </div>
            {crmError && (
                <div
                    role="alert"
                    className="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border p-4 text-sm"
                >
                    {crmError}
                </div>
            )}
            {crmSuccess && (
                <div
                    role="status"
                    className="border-primary/30 bg-primary/5 text-foreground rounded-lg border p-4 text-sm"
                >
                    {crmSuccess}
                </div>
            )}
            {!integration ? (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Conecte sua conta</CardTitle>
                        <CardDescription>
                            Você será levado ao RD Station para autorizar o
                            acesso. A conexão pertence à Empresa e continua
                            ativa mesmo se a pessoa que autorizou sair da
                            Equipe.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {permissions.canManageIntegrations ? (
                            <Button asChild>
                                <a href={connect().url}>
                                    Conectar com RD Station
                                </a>
                            </Button>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                Você não tem permissão para gerenciar
                                Integrações.
                            </p>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <Card className="max-w-2xl">
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                CRM conectado{' '}
                                <Badge
                                    variant={
                                        connected
                                            ? 'success'
                                            : integration.status === 'error'
                                              ? 'destructive'
                                              : 'secondary'
                                    }
                                >
                                    {connected && <Check className="size-3" />}
                                    {integration.statusLabel}
                                </Badge>
                            </CardTitle>
                            <CardDescription className="mt-2">
                                {teamAccountNames.length > 0
                                    ? `Conta: ${teamAccountNames.join(', ')}`
                                    : integration.accountEmail
                                      ? `Conectado como ${integration.accountEmail}`
                                      : 'Conta do RD Station CRM conectada'}
                            </CardDescription>
                            {integration.statusMessage && (
                                <p className="text-destructive mt-2 text-sm">
                                    {integration.statusMessage}
                                </p>
                            )}
                            {integration.crmMetadataLoadedAt && (
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Metadados atualizados em{' '}
                                    {new Date(
                                        integration.crmMetadataLoadedAt,
                                    ).toLocaleString('pt-BR')}
                                </p>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {permissions.canManageIntegrations && (
                            <Button asChild>
                                <a href={connect().url}>Reconectar</a>
                            </Button>
                        )}
                        {permissions.canManageIntegrations && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.delete(destroy(integration.id).url)
                                }
                            >
                                <Trash2 /> Desconectar
                            </Button>
                        )}
                    </CardContent>
                </Card>
            )}
            {connected && permissions.canManageIntegrations && (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Etapas comerciais</CardTitle>
                        <CardDescription>
                            Ao entrar em uma destas etapas, qualquer Negócio aberto pausa a IA em todas as conversas da Pessoa até a saída do funil comercial ou uma devolução manual.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {crmPipelines.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhum funil foi encontrado nesta conta.
                            </p>
                        ) : crmPipelines.map((pipeline) => (
                            <fieldset key={pipeline.id} className="grid gap-2">
                                <legend className="text-sm font-medium">{pipeline.name}</legend>
                                {pipeline.stages.map((stage) => (
                                    <label
                                        key={stage.id}
                                        className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedCommercialStageIds.includes(stage.id)}
                                            onChange={() => setSelectedCommercialStageIds((current) =>
                                                current.includes(stage.id)
                                                    ? current.filter((id) => id !== stage.id)
                                                    : [...current, stage.id],
                                            )}
                                            className="accent-primary mt-0.5 size-4"
                                        />
                                        <span>{stage.name}</span>
                                    </label>
                                ))}
                            </fieldset>
                        ))}
                        <Button
                            type="button"
                            className="w-fit"
                            onClick={() => router.put(
                                commercialStages.update().url,
                                { commercial_stage_ids: selectedCommercialStageIds },
                                { preserveScroll: true },
                            )}
                        >
                            Salvar etapas comerciais
                        </Button>
                    </CardContent>
                </Card>
            )}
            {connected && permissions.canManageIntegrations && (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Responsável padrão</CardTitle>
                        <CardDescription>
                            Novas Pessoas do CRM e Negócios sem Responsável herdam esta escolha.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <Select
                            value={selectedDefaultOwnerId}
                            onValueChange={setSelectedDefaultOwnerId}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Selecione um Responsável" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Sem Responsável padrão</SelectItem>
                                {crmOwners.map((owner) => (
                                    <SelectItem key={owner.id} value={owner.id}>
                                        {owner.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            className="w-fit"
                            onClick={() =>
                                router.put(
                                    defaultOwner.update().url,
                                    {
                                        default_owner_id:
                                            selectedDefaultOwnerId === 'none'
                                                ? null
                                                : selectedDefaultOwnerId,
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Salvar Responsável padrão
                        </Button>
                    </CardContent>
                </Card>
            )}
            {connected && permissions.canManageIntegrations && (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Campos visíveis ao Agente</CardTitle>
                        <CardDescription>
                            Escolha quais Campos personalizados da Pessoa podem ser usados na consulta da ficha. Negócios abertos e Responsável continuam disponíveis.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {crmFields.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhum Campo de Pessoa foi encontrado nesta conta.
                            </p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {crmFields.map((field) => (
                                    <label
                                        key={field.id}
                                        className="border-input hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedFieldIds.includes(field.id)}
                                            onChange={() =>
                                                setSelectedFieldIds((current) =>
                                                    current.includes(field.id)
                                                        ? current.filter((id) => id !== field.id)
                                                        : [...current, field.id],
                                                )
                                            }
                                            className="accent-primary mt-0.5 size-4"
                                        />
                                        <span>{field.name}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <Button
                            type="button"
                            className="w-fit"
                            onClick={() =>
                                router.put(
                                    visibleToAgent.update().url,
                                    { visible_field_ids: selectedFieldIds },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Salvar Campos visíveis
                        </Button>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
