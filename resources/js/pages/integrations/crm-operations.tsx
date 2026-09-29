import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import crmOperations from '@/routes/integrations/crm/operations';
import { show } from '@/routes/integrations';

type CrmOperation = {
    id: number;
    origin: string;
    type: string;
    status: string;
    attempts: number;
    failureMessage: string | null;
    summary: Record<string, unknown>;
    contactName: string;
    createdAt: string | null;
};

type Props = {
    integration: { id: string; name: string; status: string };
    operations: {
        data: CrmOperation[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    onlyFailed: boolean;
    crmSuccess: string | null;
};

const originLabels: Record<string, string> = {
    automatic: 'Automática',
    agent: 'Ação do Agente',
    resend: 'Reenvio',
};

const typeLabels: Record<string, string> = {
    guarantee_crm_deal: 'Garantir Negócio',
    update_person: 'Atualizar Pessoa',
    move_deal_stage: 'Mover Negócio',
    add_deal_note: 'Registrar nota',
};

const statusLabels: Record<string, string> = {
    pending: 'Pendente',
    processing: 'Em andamento',
    completed: 'Concluída',
    failed: 'Com falha',
};

export default function CrmOperationsPage({
    integration,
    operations,
    onlyFailed,
    crmSuccess,
}: Props) {
    const failureFilterUrl = crmOperations.index({ query: { failed: '1' } });
    const allOperationsUrl = crmOperations.index();
    const previousPageUrl = crmOperations.index({
        query: {
            page: operations.currentPage - 1,
            ...(onlyFailed ? { failed: '1' } : {}),
        },
    });
    const nextPageUrl = crmOperations.index({
        query: {
            page: operations.currentPage + 1,
            ...(onlyFailed ? { failed: '1' } : {}),
        },
    });

    return (
        <>
            <Head title="Operações do CRM" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start gap-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label="Voltar para o CRM"
                    >
                        <Link href={show('crm')}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Heading
                        title="Operações do CRM"
                        description={'Histórico de gravações solicitadas à ' + integration.name + '.'}
                    />
                </div>
                {crmSuccess && (
                    <div
                        role="status"
                        className="border-primary/30 bg-primary/5 text-foreground rounded-lg border p-4 text-sm"
                    >
                        {crmSuccess}
                    </div>
                )}
                <Card>
                    <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Histórico de gravações</CardTitle>
                            <CardDescription>{operations.total} operações</CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant={onlyFailed ? 'outline' : 'secondary'}
                                size="sm"
                                asChild
                            >
                                <Link href={allOperationsUrl}>Todas</Link>
                            </Button>
                            <Button
                                variant={onlyFailed ? 'secondary' : 'outline'}
                                size="sm"
                                asChild
                            >
                                <Link href={failureFilterUrl}>Com falha</Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {operations.data.length === 0 ? (
                            <p className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                                {onlyFailed
                                    ? 'Nenhuma operação com falha.'
                                    : 'Ainda não há operações registradas.'}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-left text-sm">
                                    <thead className="text-muted-foreground border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-3 py-3 font-medium">Contato</th>
                                            <th className="px-3 py-3 font-medium">Origem e tipo</th>
                                            <th className="px-3 py-3 font-medium">Situação</th>
                                            <th className="px-3 py-3 font-medium">Tentativas</th>
                                            <th className="px-3 py-3 font-medium">Resumo enviado</th>
                                            <th className="px-3 py-3 font-medium">Data</th>
                                            <th className="px-3 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {operations.data.map((operation) => (
                                            <tr key={operation.id} className="align-top">
                                                <td className="px-3 py-4 font-medium">{operation.contactName}</td>
                                                <td className="px-3 py-4">
                                                    <div>{originLabels[operation.origin] ?? operation.origin}</div>
                                                    <div className="text-muted-foreground mt-1 text-xs">
                                                        {typeLabels[operation.type] ?? operation.type}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4">
                                                    <Badge variant={statusVariant(operation.status)}>
                                                        {statusLabels[operation.status] ?? operation.status}
                                                    </Badge>
                                                    {operation.failureMessage && (
                                                        <p className="text-destructive mt-2 max-w-56 whitespace-normal text-xs">
                                                            {operation.failureMessage}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-4">{operation.attempts}</td>
                                                <td className="max-w-64 whitespace-normal px-3 py-4">
                                                    {summaryLines(operation.summary).map((line, index) => (
                                                        <div key={index} className="mb-1 last:mb-0">
                                                            {line}
                                                        </div>
                                                    ))}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-4 text-xs">
                                                    {operation.createdAt
                                                        ? new Date(operation.createdAt).toLocaleString('pt-BR')
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-4">
                                                    {operation.status === 'failed' && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    crmOperations.retry(operation.id).url,
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <RotateCcw />
                                                            Tentar de novo
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {operations.lastPage > 1 && (
                            <div className="flex items-center justify-between border-t pt-4">
                                <p className="text-muted-foreground text-sm">
                                    Página {operations.currentPage} de {operations.lastPage}
                                </p>
                                <div className="flex gap-2">
                                    {operations.currentPage > 1 ? (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={previousPageUrl}>Anterior</Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                                    )}
                                    {operations.currentPage < operations.lastPage ? (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={nextPageUrl}>Próxima</Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" size="sm" disabled>Próxima</Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'success' {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return status === 'processing' ? 'default' : 'secondary';
}

function summaryLines(summary: Record<string, unknown>): string[] {
    return Object.entries(summary).flatMap(([key, value]) => {
        if (key === 'linked_deal' || key.startsWith('crm_person_phone_match_')) {
            return [];
        }

        if ((key === 'contact_fields' || key === 'fields') && value !== null && typeof value === 'object') {
            return Object.entries(value).map(([field, fieldValue]) =>
                field + ': ' + displayValue(fieldValue),
            );
        }

        if (typeof value === 'string' || typeof value === 'number') {
            const label = key === 'title'
                ? 'Título'
                : key === 'estimated_value'
                    ? 'Valor estimado'
                    : key.replaceAll('_', ' ');
            const formattedValue = key === 'estimated_value'
                ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
                : String(value);

            return [label + ': ' + formattedValue];
        }

        return [];
    });
}

function displayValue(value: unknown): string {
    if (typeof value === 'string' || typeof value === 'number') {
        return String(value);
    }

    if (typeof value === 'boolean') {
        return value ? 'Sim' : 'Não';
    }

    return '—';
}
