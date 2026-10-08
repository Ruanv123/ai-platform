import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Upload } from 'lucide-react';
import { initialDraft } from '@/components/templates/template-draft';
import type { SavedTemplate } from '@/components/templates/template-draft';
import WhatsappPreview from '@/components/templates/whatsapp-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, submit } from '@/routes/templates';

type Template = SavedTemplate & {
    status: string;
    statusLabel: string;
    formatLabel: string;
    requestedCategoryLabel: string;
    assignedCategory: string | null;
    assignedCategoryLabel: string | null;
    headerMediaHandleAvailable: boolean;
    qualityRating: string | null;
    rejectionReason: string | null;
    rejectionExplanation: string | null;
    accountId: number;
    accountLabel: string;
    history?: {
        id: number;
        event: string;
        oldStatus: string | null;
        newStatus: string | null;
        oldCategory: string | null;
        newCategory: string | null;
        oldQualityRating: string | null;
        newQualityRating: string | null;
        reason: string | null;
        explanation: string | null;
        createdAt: string | null;
    }[];
};

type Props = { template: Template; permissions: { canManage: boolean } };

export default function TemplateShow({ template, permissions }: Props) {
    return (
        <>
            <Head title={`Template · ${template.name}`} />
            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <Link
                    href={index()}
                    className="text-muted-foreground hover:text-foreground flex w-fit items-center gap-2 text-sm font-medium"
                >
                    <ArrowLeft className="size-4" /> Voltar para Templates
                </Link>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {template.name}
                            </h1>
                            <Badge
                                variant={
                                    template.status === 'approved'
                                        ? 'secondary'
                                        : template.status === 'rejected'
                                          ? 'destructive'
                                          : 'outline'
                                }
                            >
                                {template.statusLabel}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {template.formatLabel} · {template.language} ·{' '}
                            {template.accountLabel}
                        </p>
                    </div>
                    {permissions.canManage &&
                        template.status !== 'approved' &&
                        template.status !== 'pending' &&
                        template.status !== 'deleted' && (
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={edit.url(template.id)}>
                                        <Pencil /> Editar Template
                                    </Link>
                                </Button>
                                <Button
                                    onClick={() =>
                                        router.post(submit.url(template.id))
                                    }
                                >
                                    <Upload /> Enviar para a Meta
                                </Button>
                            </div>
                        )}
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
                    <div className="grid gap-6">
                        <section className="bg-card rounded-xl border p-6">
                            <div className="mb-5 grid gap-1">
                                <h2 className="font-semibold">
                                    Como chega ao Contato
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    A Meta substitui as variáveis pelos valores
                                    preenchidos no envio.
                                </p>
                            </div>
                            <WhatsappPreview
                                draft={initialDraft(
                                    template,
                                    template.accountId,
                                )}
                            />
                        </section>

                        {template.rejectionExplanation && (
                            <section className="border-destructive/30 bg-destructive/5 rounded-xl border p-6">
                                <h2 className="text-destructive font-semibold">
                                    Por que a Meta recusou
                                </h2>
                                {template.rejectionReason && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Código informado pela Meta:{' '}
                                        {template.rejectionReason}
                                    </p>
                                )}
                                <p className="mt-4 text-sm leading-6">
                                    {template.rejectionExplanation}
                                </p>
                                {permissions.canManage && (
                                    <p className="text-muted-foreground mt-4 text-sm">
                                        Edite o Template e reenvie sem perder o
                                        histórico.
                                    </p>
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="grid gap-6">
                        <section className="bg-card rounded-xl border p-6">
                            <h2 className="mb-4 font-semibold">Categorias</h2>
                            <dl className="grid gap-3 text-sm">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Pedida
                                    </dt>
                                    <dd>{template.requestedCategoryLabel}</dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Atribuída pela Meta
                                    </dt>
                                    <dd>
                                        {template.assignedCategoryLabel ??
                                            'Ainda não definida'}
                                    </dd>
                                </div>
                                {template.qualityRating && (
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Qualidade
                                        </dt>
                                        <dd>{template.qualityRating}</dd>
                                    </div>
                                )}
                            </dl>
                        </section>
                        <section className="bg-card rounded-xl border p-6">
                            <h2 className="mb-4 font-semibold">
                                Variáveis posicionais
                            </h2>
                            {template.variables.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Este Template não tem variáveis.
                                </p>
                            ) : (
                                <div className="grid gap-2">
                                    {template.variables.map((variable) => (
                                        <div
                                            key={`${variable.component}-${variable.position}`}
                                            className="bg-muted/50 flex items-center justify-between rounded-md px-3 py-2 text-sm"
                                        >
                                            <code>{`{{${variable.position}}}`}</code>
                                            <span className="text-muted-foreground">
                                                {variable.component === 'header'
                                                    ? 'Cabeçalho'
                                                    : variable.component ===
                                                        'button'
                                                      ? `URL do botão ${(variable.buttonIndex ?? 0) + 1}`
                                                      : 'Corpo'}
                                            </span>
                                            {variable.description && (
                                                <span>
                                                    {variable.description}
                                                </span>
                                            )}
                                            {variable.example && (
                                                <span className="text-muted-foreground">
                                                    Exemplo: {variable.example}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                        {template.history && template.history.length > 0 && (
                            <section className="bg-card rounded-xl border p-6">
                                <h2 className="mb-4 font-semibold">
                                    Histórico
                                </h2>
                                <div className="grid gap-4">
                                    {template.history.map((entry) => (
                                        <div
                                            key={entry.id}
                                            className="border-border/70 border-l-2 pl-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {historyLabel(entry.event)}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {entry.newQualityRating
                                                    ? `${entry.oldQualityRating ?? 'Sem classificação'} → ${entry.newQualityRating}`
                                                    : (entry.newStatus ??
                                                      entry.newCategory ??
                                                      'Registro')}
                                                {entry.createdAt
                                                    ? ` · ${new Date(entry.createdAt).toLocaleString('pt-BR')}`
                                                    : ''}
                                            </p>
                                            {entry.explanation && (
                                                <p className="text-muted-foreground mt-2 text-xs leading-5">
                                                    {entry.explanation}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function historyLabel(event: string): string {
    return (
        {
            imported: 'Template sincronizado',
            submitted: 'Enviado para análise',
            status_changed: 'Status atualizado',
            category_changed: 'Categoria recategorizada',
            quality_changed: 'Qualidade atualizada',
            deleted: 'Removido na Meta',
        }[event] ?? 'Alteração registrada'
    );
}
