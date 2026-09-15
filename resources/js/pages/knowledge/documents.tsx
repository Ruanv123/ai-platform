import { Form, Head, Link, router, usePoll } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    FileText,
    LoaderCircle,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index,
    move,
    reprocess,
    store,
    update,
} from '@/routes/documents';
import type { TeamPermissions } from '@/types';

type KnowledgeDocument = {
    id: number;
    title: string;
    content: string;
    type: string;
    status: string;
    statusLabel: string;
    errorMessage: string | null;
    sizeBytes: number;
    updatedAt: string | null;
};

type KnowledgeCollection = {
    id: number;
    name: string;
};

type Props = {
    collection: KnowledgeCollection & { description: string | null };
    documents: KnowledgeDocument[];
    collections: KnowledgeCollection[];
    storage: {
        usedBytes: number;
        limitBytes: number;
        usedPercentage: number;
    };
    polling: boolean;
    permissions: TeamPermissions;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function statusVariant(
    status: string,
): 'secondary' | 'default' | 'destructive' {
    if (status === 'ready') {
        return 'default';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return 'secondary';
}

type DocumentFormDialogProps = {
    collectionId: number;
    document: KnowledgeDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function DocumentFormDialog({
    collectionId,
    document,
    open,
    onOpenChange,
}: DocumentFormDialogProps) {
    const isEditing = document !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? 'Editar documento'
                            : 'Novo documento de texto'}
                    </DialogTitle>
                    <DialogDescription>
                        Escreva um conteúdo que os Agentes poderão consultar na
                        base de conhecimento.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    key={document?.id ?? 'new'}
                    {...(isEditing
                        ? update.form(document.id)
                        : store.form(collectionId))}
                    className="grid gap-5"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="document-title">Título</Label>
                                <Input
                                    id="document-title"
                                    name="title"
                                    defaultValue={document?.title ?? ''}
                                    placeholder="Ex.: Política de trocas"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="document-content">
                                    Conteúdo
                                </Label>
                                <textarea
                                    id="document-content"
                                    name="content"
                                    defaultValue={document?.content ?? ''}
                                    placeholder="Use títulos e parágrafos para organizar o conteúdo."
                                    className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-64 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    required
                                />
                                <InputError message={errors.content} />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Salvando...'
                                        : isEditing
                                          ? 'Salvar e reprocessar'
                                          : 'Criar documento'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Documents({
    collection,
    documents,
    collections,
    storage,
    polling,
    permissions,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [documentToEdit, setDocumentToEdit] =
        useState<KnowledgeDocument | null>(null);
    const { start, stop } = usePoll(
        5000,
        { only: ['documents', 'polling', 'storage'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (polling) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [polling, start, stop]);

    const openCreateDialog = () => {
        setDocumentToEdit(null);
        setFormOpen(true);
    };

    const openEditDialog = (document: KnowledgeDocument) => {
        setDocumentToEdit(document);
        setFormOpen(true);
    };

    return (
        <>
            <Head title={`${collection.name} · Documentos`} />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="grid gap-3">
                        <Link
                            href={index.url(collection.id)}
                            className="text-muted-foreground inline-flex items-center gap-2 text-sm hover:underline"
                        >
                            <ArrowLeft className="size-4" />
                            Base de conhecimento
                        </Link>
                        <Heading
                            title={collection.name}
                            description={
                                collection.description ||
                                'Documentos consultáveis pelos seus Agentes.'
                            }
                        />
                    </div>
                    {permissions.canManageKnowledge && (
                        <Button onClick={openCreateDialog}>
                            <Plus />
                            Novo documento
                        </Button>
                    )}
                </div>

                <Card className="shadow-none">
                    <CardHeader className="gap-3">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle className="text-base">
                                    Armazenamento da Empresa
                                </CardTitle>
                                <CardDescription>
                                    {formatBytes(storage.usedBytes)} de{' '}
                                    {formatBytes(storage.limitBytes)} utilizados
                                </CardDescription>
                            </div>
                            <span className="text-muted-foreground text-sm">
                                {storage.usedPercentage}%
                            </span>
                        </div>
                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                            <div
                                className="bg-primary h-full rounded-full transition-all"
                                style={{ width: `${storage.usedPercentage}%` }}
                            />
                        </div>
                    </CardHeader>
                </Card>

                {documents.length === 0 ? (
                    <Card className="border-dashed shadow-none">
                        <CardContent className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-xl">
                                <FileText className="text-muted-foreground size-6" />
                            </div>
                            <div className="grid gap-1">
                                <h2 className="font-semibold">
                                    Nenhum documento nesta coleção
                                </h2>
                                <p className="text-muted-foreground max-w-sm text-sm">
                                    Adicione um texto livre para começar a
                                    alimentar sua base de conhecimento.
                                </p>
                            </div>
                            {permissions.canManageKnowledge && (
                                <Button
                                    variant="outline"
                                    onClick={openCreateDialog}
                                >
                                    <Plus />
                                    Criar primeiro documento
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {documents.map((document) => (
                            <Card key={document.id} className="shadow-none">
                                <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 items-start gap-3">
                                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg">
                                            <FileText className="text-muted-foreground size-5" />
                                        </div>
                                        <div className="grid min-w-0 gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate font-medium">
                                                    {document.title}
                                                </h2>
                                                <Badge
                                                    variant={statusVariant(
                                                        document.status,
                                                    )}
                                                >
                                                    {document.status ===
                                                        'processing' && (
                                                        <LoaderCircle className="animate-spin" />
                                                    )}
                                                    {document.statusLabel}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground text-sm">
                                                {formatBytes(
                                                    document.sizeBytes,
                                                )}{' '}
                                                · atualizado em{' '}
                                                {document.updatedAt
                                                    ? new Date(
                                                          document.updatedAt,
                                                      ).toLocaleDateString(
                                                          'pt-BR',
                                                      )
                                                    : 'agora'}
                                            </p>
                                            {document.status === 'failed' &&
                                                document.errorMessage && (
                                                    <p className="text-destructive flex items-center gap-1 text-sm">
                                                        <AlertCircle className="size-4" />
                                                        {document.errorMessage}
                                                    </p>
                                                )}
                                        </div>
                                    </div>
                                    {permissions.canManageKnowledge && (
                                        <div className="flex shrink-0 items-center gap-1">
                                            {document.status === 'failed' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            reprocess(
                                                                document.id,
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    <RefreshCw />
                                                    Reprocessar
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    openEditDialog(document)
                                                }
                                                aria-label="Editar documento"
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `Excluir o documento “${document.title}”?`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            destroy(document.id)
                                                                .url,
                                                        );
                                                    }
                                                }}
                                                aria-label="Excluir documento"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                                {permissions.canManageKnowledge && (
                                    <CardContent className="border-t pt-4">
                                        <Form
                                            {...move.form(document.id)}
                                            className="flex items-center gap-2"
                                        >
                                            <Label
                                                htmlFor={`move-${document.id}`}
                                                className="text-muted-foreground text-xs"
                                            >
                                                Mover para
                                            </Label>
                                            <select
                                                id={`move-${document.id}`}
                                                name="collection_id"
                                                defaultValue={collection.id}
                                                className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                                            >
                                                {collections.map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                            >
                                                Mover
                                            </Button>
                                        </Form>
                                    </CardContent>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>
            <DocumentFormDialog
                collectionId={collection.id}
                document={documentToEdit}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
        </>
    );
}
