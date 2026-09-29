import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/heading';
import { destroy, index } from '@/routes/integrations';
import { connect } from '@/routes/integrations/crm/rd-station';
import type { TeamPermissions } from '@/types';

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
    permissions: TeamPermissions;
};

export default function CrmConnectionPanel({ integration, crmError, crmSuccess, permissions }: Props) {
    const connected = integration?.status === 'connected';
    const teamAccountNames = integration?.crmAccount?.teams.map((team) => `${team.name} (ID ${team.id})`) ?? [];

    return (
        <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
            <div className="flex items-start gap-3">
                <Button variant="ghost" size="icon" asChild aria-label="Voltar para integrações"><Link href={index()}><ArrowLeft /></Link></Button>
                <Heading title="RD Station CRM" description="Conecte a conta da Empresa pela autorização oficial do RD Station CRM." />
            </div>
            {crmError && <div role="alert" className="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border p-4 text-sm">{crmError}</div>}
            {crmSuccess && <div role="status" className="border-primary/30 bg-primary/5 text-foreground rounded-lg border p-4 text-sm">{crmSuccess}</div>}
            {!integration ? (
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Conecte sua conta</CardTitle>
                        <CardDescription>Você será levado ao RD Station para autorizar o acesso. A conexão pertence à Empresa e continua ativa mesmo se a pessoa que autorizou sair da Equipe.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {permissions.canManageIntegrations ? <Button asChild><a href={connect().url}>Conectar com RD Station</a></Button> : <p className="text-muted-foreground text-sm">Você não tem permissão para gerenciar Integrações.</p>}
                    </CardContent>
                </Card>
            ) : (
                <Card className="max-w-2xl">
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">CRM conectado <Badge variant={connected ? 'success' : integration.status === 'error' ? 'destructive' : 'secondary'}>{connected && <Check className="size-3" />}{integration.statusLabel}</Badge></CardTitle>
                            <CardDescription className="mt-2">{teamAccountNames.length > 0 ? `Conta: ${teamAccountNames.join(', ')}` : integration.accountEmail ? `Conectado como ${integration.accountEmail}` : 'Conta do RD Station CRM conectada'}</CardDescription>
                            {integration.statusMessage && <p className="text-destructive mt-2 text-sm">{integration.statusMessage}</p>}
                            {integration.crmMetadataLoadedAt && <p className="text-muted-foreground mt-2 text-xs">Metadados atualizados em {new Date(integration.crmMetadataLoadedAt).toLocaleString('pt-BR')}</p>}
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {permissions.canManageIntegrations && <Button asChild><a href={connect().url}>Reconectar</a></Button>}
                        {permissions.canManageIntegrations && <Button variant="outline" onClick={() => router.delete(destroy(integration.id).url)}><Trash2 /> Desconectar</Button>}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
