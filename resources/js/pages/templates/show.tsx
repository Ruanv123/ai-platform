import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, submit, update } from '@/routes/templates';

type Template = {
    id: number;
    name: string;
    language: string;
    status: string;
    statusLabel: string;
    requestedCategory: string;
    requestedCategoryLabel: string;
    assignedCategory: string | null;
    assignedCategoryLabel: string | null;
    body: string;
    headerType: string | null;
    headerText: string | null;
    headerMediaHandleAvailable: boolean;
    footer: string | null;
    buttons: {
        type: string;
        text?: string;
        url?: string;
        phone_number?: string;
        example?: string;
    }[];
    variables: {
        position: number;
        component: string;
        example: string | null;
        buttonIndex?: number;
    }[];
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
        reason: string | null;
        explanation: string | null;
        createdAt: string | null;
    }[];
};

type Props = { template: Template; permissions: { canManage: boolean } };

export default function TemplateShow({ template, permissions }: Props) {
    const preview = template.body.replace(
        /{{\s*(\d+)\s*}}/g,
        (_match, position: string) => `valor ${position}`,
    );

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
                            {template.language} · {template.accountLabel}
                        </p>
                    </div>
                    {permissions.canManage &&
                        template.status !== 'approved' &&
                        template.status !== 'pending' &&
                        template.status !== 'deleted' && (
                            <Button
                                onClick={() =>
                                    router.post(submit.url(template.id))
                                }
                            >
                                <Upload /> Enviar para a Meta
                            </Button>
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
                                    do Fluxo.
                                </p>
                            </div>
                            <div className="bg-muted/50 max-w-md rounded-2xl p-4">
                                {template.headerType === 'IMAGE' && (
                                    <p className="mb-2 font-semibold">Imagem</p>
                                )}
                                {template.headerType === 'DOCUMENT' && (
                                    <p className="mb-2 font-semibold">Documento</p>
                                )}
                                {template.headerType === 'VIDEO' && (
                                    <p className="text-signal-attention mb-2 text-sm font-medium">
                                        Cabeçalho de vídeo sincronizado da Meta. O envio pela conversa ainda não está disponível.
                                    </p>
                                )}
                                {template.headerText && (
                                    <p className="mb-2 font-semibold">
                                        {template.headerText}
                                    </p>
                                )}
                                <p className="text-sm leading-6 whitespace-pre-wrap">
                                    {preview}
                                </p>
                                {template.footer && (
                                    <p className="text-muted-foreground mt-3 text-xs">
                                        {template.footer}
                                    </p>
                                )}
                                {template.buttons.length > 0 && (
                                    <div className="mt-4 grid gap-2">
                                        {template.buttons.map(
                                            (button, index) => (
                                                <div
                                                    key={`${button.text}-${index}`}
                                                    className="text-primary border-primary/20 bg-background rounded-md border px-3 py-2 text-center text-sm"
                                                >
                                                    {button.text ??
                                                        (button.type ===
                                                        'COPY_CODE'
                                                            ? 'Copiar código'
                                                            : button.type)}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
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
                                        Corrija o conteúdo abaixo e reenvie sem
                                        perder o histórico.
                                    </p>
                                )}
                            </section>
                        )}

                        {permissions.canManage &&
                            template.status !== 'approved' &&
                            template.status !== 'pending' &&
                            template.status !== 'deleted' && (
                                <section className="bg-card rounded-xl border p-6">
                                    <h2 className="mb-5 font-semibold">
                                        Corrigir e reenviar
                                    </h2>
                                    <Form
                                        action={update.url(template.id)}
                                        method="put"
                                        className="grid gap-4"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="whatsapp_account_id"
                                                    value={template.accountId}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="name"
                                                    value={template.name}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="language"
                                                    value={template.language}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="requested_category"
                                                    value={
                                                        template.requestedCategory
                                                    }
                                                />
                                                <input
                                                    type="hidden"
                                                    name="header_type"
                                                    value={template.headerType ?? 'TEXT'}
                                                />
                                                <div className="grid gap-2">
                                                    <Label htmlFor="body">
                                                        Corpo
                                                    </Label>
                                                    <textarea
                                                        id="body"
                                                        name="body"
                                                        defaultValue={
                                                            template.body
                                                        }
                                                        className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                                    />
                                                    {errors.body && (
                                                        <p className="text-destructive text-xs">
                                                            {errors.body}
                                                        </p>
                                                    )}
                                                </div>
                                                {template.headerType === 'IMAGE' ||
                                                template.headerType === 'DOCUMENT' ? (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="header_media">
                                                            {template.headerType === 'IMAGE'
                                                                ? 'Novo exemplo de imagem · JPEG ou PNG · até 5 MB'
                                                                : 'Novo exemplo de documento · PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT · até 100 MB'}
                                                        </Label>
                                                        <input
                                                            id="header_media"
                                                            name="header_media"
                                                            type="file"
                                                            required={!template.headerMediaHandleAvailable}
                                                            accept={template.headerType === 'IMAGE'
                                                                ? '.jpg,.jpeg,.png,image/jpeg,image/png'
                                                                : '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt'}
                                                            className="border-input file:bg-muted file:text-foreground rounded-md border px-3 py-2 text-xs file:mr-3 file:rounded file:border-0 file:px-2 file:py-1"
                                                        />
                                                        {errors.header_media && (
                                                            <p className="text-destructive text-xs">
                                                                {errors.header_media}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : template.headerType === 'TEXT' ? (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="header">
                                                            Cabeçalho
                                                        </Label>
                                                        <Input
                                                            id="header"
                                                            name="header_text"
                                                            defaultValue={template.headerText ?? ''}
                                                        />
                                                    </div>
                                                ) : null}
                                                <div className="grid gap-2">
                                                    <Label htmlFor="footer">
                                                        Rodapé
                                                    </Label>
                                                    <Input
                                                        id="footer"
                                                        name="footer"
                                                        defaultValue={
                                                            template.footer ??
                                                            ''
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    <Check /> Salvar correção
                                                </Button>
                                            </>
                                        )}
                                    </Form>
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
                                            {variable.example && (
                                                <span className="text-muted-foreground">
                                                    {variable.example}
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
                                                {entry.newStatus ??
                                                    entry.newCategory ??
                                                    'Registro'}
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
            deleted: 'Removido na Meta',
        }[event] ?? 'Alteração registrada'
    );
}
