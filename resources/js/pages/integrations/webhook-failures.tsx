import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, RefreshCw, Webhook } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { retry } from '@/routes/integrations/webhook-failures';
import { index } from '@/routes/integrations';
import type { TeamPermissions } from '@/types';

type Failure = {
    id: string;
    event: string;
    destination: string;
    responseStatus: number | null;
    reason: string | null;
    occurredAt: string | null;
    attempts: number;
};
type Props = {
    failures: { data: Failure[]; total: number };
    permissions: TeamPermissions;
};

export default function WebhookFailures({ failures, permissions }: Props) {
    return (
        <>
            <Head title="Falhas de webhook" />
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
                        title="Falhas de webhook"
                        description="Entregas esgotadas ficam aqui para você investigar e reenviar."
                    />
                </div>
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <CardTitle className="text-sm">
                                Fila de falhas
                            </CardTitle>
                            <Badge
                                variant={
                                    failures.total > 0
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {failures.total}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {failures.data.length === 0 ? (
                            <div className="text-muted-foreground flex min-h-40 flex-col items-center justify-center gap-2 p-6 text-center">
                                <Webhook className="size-6" />
                                <p className="text-sm">
                                    Nenhuma entrega esgotada.
                                </p>
                            </div>
                        ) : (
                            <div className="divide-border divide-y">
                                {failures.data.map((failure) => (
                                    <div
                                        key={failure.id}
                                        className="flex flex-wrap items-center gap-4 p-5"
                                    >
                                        <div className="bg-destructive/10 text-destructive flex size-9 shrink-0 items-center justify-center rounded-lg">
                                            <Webhook className="size-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold">
                                                {failure.event}
                                            </p>
                                            <p className="text-muted-foreground flex items-center gap-1 truncate text-xs">
                                                <ExternalLink className="size-3" />
                                                {failure.destination}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {failure.reason ??
                                                    'Sem motivo informado'}{' '}
                                                ·{' '}
                                                {failure.responseStatus ??
                                                    'sem resposta'}{' '}
                                                · {failure.attempts} tentativas
                                            </p>
                                        </div>
                                        {permissions.canManageIntegrations && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        retry(failure.id).url,
                                                    )
                                                }
                                            >
                                                <RefreshCw /> Reenviar
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
