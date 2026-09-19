import { Form, Head, Link, router, useHttp } from '@inertiajs/react';
import {
    type ColumnDef,
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import {
    AlertCircle,
    ArrowLeft,
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    File,
    FileText,
    FileQuestion,
    Globe,
    LoaderCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ConditionalPoll } from '@/hooks/use-conditional-poll';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    destroy,
    move,
    reprocess,
    show,
    store,
    update,
    download,
    index as documentsIndex,
} from '@/routes/documents';
import { index as collectionIndex } from '@/routes/collections';
import type { TeamPermissions } from '@/types';

type KnowledgeDocument = {
    id: number;
    title: string;
    content?: string;
    faqItems?: FaqItem[] | null;
    type: string;
    status: string;
    statusLabel: string;
    errorMessage: string | null;
    sizeBytes: number;
    originalFilename: string | null;
    mimeType: string | null;
    downloadUrl: string | null;
    url: string | null;
    updatedAt: string | null;
};

type FaqItem = {
    question: string;
    answer: string;
};

type KnowledgeCollection = {
    id: string;
    name: string;
};

type DocumentSort = 'title' | 'size' | 'updated_at';
type SortDirection = 'asc' | 'desc';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedDocuments = {
    data: KnowledgeDocument[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Props = {
    collection: KnowledgeCollection & { description: string | null };
    documents: PaginatedDocuments;
    collections: KnowledgeCollection[];
    storage: {
        usedBytes: number;
        limitBytes: number;
        usedPercentage: number;
    };
    polling: boolean;
    permissions: TeamPermissions;
    filters: {
        search: string;
        type: string;
        status: string;
        sort: DocumentSort;
        direction: SortDirection;
    };
};

const documentTableFeatures = tableFeatures({
    rowPaginationFeature,
    rowSortingFeature,
});

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
    collectionId: string;
    document: KnowledgeDocument | null;
    open: boolean;
    loading: boolean;
    loadError: string | null;
    onOpenChange: (open: boolean) => void;
    onRetry: () => void;
};

function DocumentFormDialog({
    collectionId,
    document,
    open,
    loading,
    loadError,
    onOpenChange,
    onRetry,
}: DocumentFormDialogProps) {
    const isEditing = document !== null;
    const [documentType, setDocumentType] = useState<
        'text' | 'faq' | 'file' | 'url'
    >(
        document?.type === 'faq'
            ? 'faq'
            : document?.type === 'file'
              ? 'file'
              : document?.type === 'url'
                ? 'url'
                : 'text',
    );
    const [faqItems, setFaqItems] = useState<FaqItem[]>(
        document?.faqItems?.length
            ? document.faqItems
            : [{ question: '', answer: '' }],
    );

    const updateFaqItem = (
        index: number,
        field: keyof FaqItem,
        value: string,
    ) => {
        setFaqItems((items) =>
            items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [field]: value } : item,
            ),
        );
    };

    const addFaqItem = () => {
        setFaqItems((items) => [...items, { question: '', answer: '' }]);
    };

    const removeFaqItem = (index: number) => {
        setFaqItems((items) =>
            items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const moveFaqItem = (index: number, direction: -1 | 1) => {
        const targetIndex = index + direction;

        if (targetIndex < 0 || targetIndex >= faqItems.length) {
            return;
        }

        setFaqItems((items) => {
            const nextItems = [...items];
            [nextItems[index], nextItems[targetIndex]] = [
                nextItems[targetIndex],
                nextItems[index],
            ];

            return nextItems;
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? 'Editar documento'
                            : documentType === 'faq'
                              ? 'Novo documento de FAQ'
                              : documentType === 'file'
                                ? 'Novo documento de arquivo'
                                : documentType === 'url'
                                  ? 'Novo documento de URL'
                                  : 'Novo documento de texto'}
                    </DialogTitle>
                    <DialogDescription>
                        {documentType === 'faq'
                            ? 'Organize perguntas e respostas que os Agentes poderão consultar.'
                            : documentType === 'file'
                              ? 'Envie um PDF, DOCX ou TXT para alimentar a base de conhecimento.'
                              : documentType === 'url'
                                ? 'Sincronize o conteúdo principal de uma página para alimentar a base de conhecimento.'
                                : 'Escreva um conteúdo que os Agentes poderão consultar na base de conhecimento.'}
                    </DialogDescription>
                </DialogHeader>
                {loading ? (
                    <div
                        className="text-muted-foreground flex min-h-48 flex-col items-center justify-center gap-3 text-center"
                        role="status"
                    >
                        <LoaderCircle className="text-primary size-6 animate-spin" />
                        <p className="text-sm">
                            Carregando conteúdo do documento...
                        </p>
                    </div>
                ) : loadError !== null ? (
                    <div
                        className="flex min-h-48 flex-col items-center justify-center gap-4 text-center"
                        role="alert"
                    >
                        <AlertCircle className="text-destructive size-6" />
                        <p className="text-muted-foreground max-w-sm text-sm">
                            {loadError}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onRetry}
                        >
                            Tentar novamente
                        </Button>
                    </div>
                ) : (
                    <Form
                        key={document?.id ?? 'new'}
                        {...(isEditing
                            ? update.form(document.id, { mergeQuery: {} })
                            : store.form(collectionId, { mergeQuery: {} }))}
                        className="grid gap-5"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="type"
                                    value={documentType}
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="document-title">
                                        Título
                                    </Label>
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
                                    <Label htmlFor="document-type">Tipo</Label>
                                    <select
                                        id="document-type"
                                        value={documentType}
                                        onChange={(event) =>
                                            setDocumentType(
                                                event.target.value as
                                                    | 'text'
                                                    | 'faq'
                                                    | 'file'
                                                    | 'url',
                                            )
                                        }
                                        disabled={isEditing}
                                        className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm shadow-xs outline-none"
                                    >
                                        <option value="text">
                                            Texto livre
                                        </option>
                                        <option value="faq">FAQ</option>
                                        <option value="file">Arquivo</option>
                                        <option value="url">URL</option>
                                    </select>
                                    <InputError message={errors.type} />
                                </div>
                                {documentType === 'faq' ? (
                                    <div className="grid gap-3">
                                        <div className="flex items-center justify-between gap-4">
                                            <div className="grid gap-1">
                                                <Label>
                                                    Perguntas e respostas
                                                </Label>
                                                <p className="text-muted-foreground text-xs">
                                                    Cada par será indexado como
                                                    um trecho separado.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addFaqItem}
                                            >
                                                <Plus />
                                                Adicionar par
                                            </Button>
                                        </div>
                                        <InputError message={errors.items} />
                                        <InputError message={errors.content} />
                                        <div className="grid gap-3">
                                            {faqItems.map((item, index) => (
                                                <div
                                                    key={index}
                                                    className="bg-muted/30 grid gap-3 rounded-lg border p-4"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-sm font-medium">
                                                            Par {index + 1}
                                                        </span>
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    moveFaqItem(
                                                                        index,
                                                                        -1,
                                                                    )
                                                                }
                                                                disabled={
                                                                    index === 0
                                                                }
                                                                aria-label={`Mover par ${index + 1} para cima`}
                                                            >
                                                                <ArrowUp />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    moveFaqItem(
                                                                        index,
                                                                        1,
                                                                    )
                                                                }
                                                                disabled={
                                                                    index ===
                                                                    faqItems.length -
                                                                        1
                                                                }
                                                                aria-label={`Mover par ${index + 1} para baixo`}
                                                            >
                                                                <ArrowDown />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    removeFaqItem(
                                                                        index,
                                                                    )
                                                                }
                                                                disabled={
                                                                    faqItems.length ===
                                                                    1
                                                                }
                                                                aria-label={`Remover par ${index + 1}`}
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`faq-question-${index}`}
                                                        >
                                                            Pergunta
                                                        </Label>
                                                        <Input
                                                            id={`faq-question-${index}`}
                                                            name={`items[${index}][question]`}
                                                            value={
                                                                item.question
                                                            }
                                                            onChange={(event) =>
                                                                updateFaqItem(
                                                                    index,
                                                                    'question',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Ex.: Qual é o prazo de entrega?"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `items.${index}.question`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`faq-answer-${index}`}
                                                        >
                                                            Resposta
                                                        </Label>
                                                        <textarea
                                                            id={`faq-answer-${index}`}
                                                            name={`items[${index}][answer]`}
                                                            value={item.answer}
                                                            onChange={(event) =>
                                                                updateFaqItem(
                                                                    index,
                                                                    'answer',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Escreva uma resposta objetiva."
                                                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `items.${index}.answer`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ) : documentType === 'file' ? (
                                    <div className="grid gap-3">
                                        <div className="grid gap-1">
                                            <Label htmlFor="document-file">
                                                Arquivo
                                            </Label>
                                            <p className="text-muted-foreground text-xs">
                                                Formatos aceitos: PDF, DOCX ou
                                                TXT · máximo de 20 MB.
                                            </p>
                                        </div>
                                        {isEditing &&
                                            document?.originalFilename && (
                                                <p className="text-muted-foreground text-sm">
                                                    Arquivo atual:{' '}
                                                    <strong className="text-foreground">
                                                        {
                                                            document.originalFilename
                                                        }
                                                    </strong>
                                                </p>
                                            )}
                                        <Input
                                            id="document-file"
                                            name="file"
                                            type="file"
                                            accept=".pdf,.docx,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain"
                                            required={!isEditing}
                                        />
                                        <InputError message={errors.file} />
                                    </div>
                                ) : documentType === 'url' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="document-url">
                                            URL da página
                                        </Label>
                                        <Input
                                            id="document-url"
                                            name="url"
                                            type="url"
                                            defaultValue={document?.url ?? ''}
                                            placeholder="https://exemplo.com/manual"
                                            required
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            O menu, rodapé e avisos da página
                                            serão descartados automaticamente.
                                        </p>
                                        <InputError message={errors.url} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="document-content">
                                            Conteúdo
                                        </Label>
                                        <textarea
                                            id="document-content"
                                            name="content"
                                            defaultValue={
                                                document?.content ?? ''
                                            }
                                            placeholder="Use títulos e parágrafos para organizar o conteúdo."
                                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-64 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                            required
                                        />
                                        <InputError message={errors.content} />
                                    </div>
                                )}
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
                )}
            </DialogContent>
        </Dialog>
    );
}

type SortHeaderProps = {
    columnId: DocumentSort;
    label: string;
    sort: DocumentSort;
    direction: SortDirection;
    onSort: (columnId: DocumentSort) => void;
};

function SortHeader({
    columnId,
    label,
    sort,
    direction,
    onSort,
}: SortHeaderProps) {
    const isActive = sort === columnId;
    const SortIcon = !isActive
        ? ChevronsUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <button
            type="button"
            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-xs font-medium tracking-wide uppercase transition-colors"
            onClick={() => onSort(columnId)}
            aria-label={'Ordenar por ' + label}
        >
            {label}
            <SortIcon className="size-3.5" />
        </button>
    );
}

function DocumentTypeIcon({ type }: { type: string }) {
    const Icon =
        type === 'faq'
            ? FileQuestion
            : type === 'file'
              ? File
              : type === 'url'
                ? Globe
                : FileText;

    return (
        <div className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
            <Icon className="size-4.5" />
        </div>
    );
}

function documentTypeLabel(type: string): string {
    return type === 'faq'
        ? 'FAQ'
        : type === 'file'
          ? 'Arquivo'
          : type === 'url'
            ? 'URL'
            : 'Texto livre';
}

function DocumentTitleCell({ document }: { document: KnowledgeDocument }) {
    return (
        <div className="flex min-w-56 items-start gap-3">
            <DocumentTypeIcon type={document.type} />
            <div className="grid min-w-0 gap-1">
                <span className="truncate font-medium">{document.title}</span>
                {document.status === 'failed' && document.errorMessage && (
                    <span className="text-destructive inline-flex items-start gap-1 text-xs">
                        <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                        <span className="line-clamp-2">
                            {document.errorMessage}
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}

function DocumentStatusCell({ document }: { document: KnowledgeDocument }) {
    return (
        <Badge variant={statusVariant(document.status)}>
            {(document.status === 'pending' ||
                document.status === 'processing') && (
                <LoaderCircle className="animate-spin" />
            )}
            {document.statusLabel}
        </Badge>
    );
}

type DocumentActionMenuProps = {
    document: KnowledgeDocument;
    onEdit: (document: KnowledgeDocument) => void;
    onMove: (document: KnowledgeDocument) => void;
    onDelete: (document: KnowledgeDocument) => void;
};

function DocumentActionMenu({
    document,
    onEdit,
    onMove,
    onDelete,
}: DocumentActionMenuProps) {
    const canReprocess =
        (document.type === 'url' && document.status !== 'processing') ||
        (document.type !== 'url' && document.status === 'failed');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={'Ações de ' + document.title}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={() => onEdit(document)}>
                    <Pencil />
                    Editar
                </DropdownMenuItem>
                {document.downloadUrl && (
                    <DropdownMenuItem asChild>
                        <a href={download.url(document.id)}>
                            <File />
                            Baixar
                        </a>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onSelect={() => onMove(document)}>
                    <ArrowDown />
                    Mover
                </DropdownMenuItem>
                {canReprocess && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                reprocess(document.id, {
                                    mergeQuery: {},
                                }).url,
                            )
                        }
                    >
                        <RefreshCw />
                        {document.type === 'url'
                            ? 'Sincronizar'
                            : 'Reprocessar'}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    onSelect={() => onDelete(document)}
                >
                    <Trash2 />
                    Excluir
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

type DocumentMoveDialogProps = {
    collectionId: string;
    collections: KnowledgeCollection[];
    document: KnowledgeDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function DocumentMoveDialog({
    collectionId,
    collections,
    document,
    open,
    onOpenChange,
}: DocumentMoveDialogProps) {
    if (document === null) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Mover documento</DialogTitle>
                    <DialogDescription>
                        Escolha a Coleção que deve receber “{document.title}”.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    key={document.id}
                    {...move.form(document.id, { mergeQuery: {} })}
                    className="grid gap-5"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="move-document-collection">
                                    Coleção de destino
                                </Label>
                                <select
                                    id="move-document-collection"
                                    name="collection_id"
                                    defaultValue={collectionId}
                                    className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                >
                                    {collections.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Movendo...'
                                        : 'Mover documento'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PaginationControls({
    documents,
    onPageChange,
}: {
    documents: PaginatedDocuments;
    onPageChange: (page: number) => void;
}) {
    const hasPreviousPage = documents.current_page > 1;
    const hasNextPage = documents.current_page < documents.last_page;

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm">
                Mostrando{' '}
                <span className="text-foreground font-medium">
                    {documents.from ?? 0}–{documents.to ?? 0}
                </span>{' '}
                de{' '}
                <span className="text-foreground font-medium">
                    {documents.total}
                </span>
            </p>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => onPageChange(documents.current_page - 1)}
                    disabled={!hasPreviousPage}
                    aria-label="Página anterior"
                >
                    <ChevronLeft />
                </Button>
                <span className="text-muted-foreground min-w-24 text-center text-sm tabular-nums">
                    Página {documents.current_page} de {documents.last_page}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => onPageChange(documents.current_page + 1)}
                    disabled={!hasNextPage}
                    aria-label="Próxima página"
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}

export default function Documents({
    collection,
    documents,
    collections,
    storage,
    polling,
    permissions,
    filters,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [documentToEdit, setDocumentToEdit] =
        useState<KnowledgeDocument | null>(null);
    const [documentToMove, setDocumentToMove] =
        useState<KnowledgeDocument | null>(null);
    const [documentLoadError, setDocumentLoadError] = useState<string | null>(
        null,
    );
    const {
        get: getDocument,
        processing: loadingDocument,
        cancel: cancelDocumentRequest,
    } = useHttp<{}, KnowledgeDocument>();

    const navigateWithFilters = (changes: {
        search?: string | null;
        type?: string | null;
        status?: string | null;
        sort?: DocumentSort;
        direction?: SortDirection;
        page?: number;
    }) => {
        const search = 'search' in changes ? changes.search : filters.search;
        const type = 'type' in changes ? changes.type : filters.type;
        const status = 'status' in changes ? changes.status : filters.status;
        const sort = changes.sort ?? filters.sort;
        const direction = changes.direction ?? filters.direction;
        const page = changes.page ?? 1;
        const query: Record<string, string | number> = {};

        if (search) {
            query.search = search;
        }

        if (type) {
            query.type = type;
        }

        if (status) {
            query.status = status;
        }

        if (sort !== 'updated_at' || direction !== 'desc') {
            query.sort = sort;
            query.direction = direction;
        }

        if (page > 1) {
            query.page = page;
        }

        router.get(
            documentsIndex.url(collection.id, { query }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: changes.page === undefined,
            },
        );
    };

    const openCreateDialog = () => {
        cancelDocumentRequest();
        setDocumentLoadError(null);
        setDocumentToEdit(null);
        setFormOpen(true);
    };

    const handleDocumentLoadError = () => {
        setDocumentLoadError(
            'Não foi possível carregar o conteúdo do documento.',
        );
    };

    const openEditDialog = (document: KnowledgeDocument) => {
        cancelDocumentRequest();
        setDocumentLoadError(null);
        setDocumentToEdit(document);
        setFormOpen(true);
        void getDocument(show.url(document.id), {
            onHttpException: handleDocumentLoadError,
            onNetworkError: handleDocumentLoadError,
            onSuccess: (completeDocument) => {
                setDocumentToEdit(completeDocument);
            },
        }).catch(() => {});
    };

    const handleFormOpenChange = (open: boolean) => {
        if (!open) {
            cancelDocumentRequest();
        }

        setFormOpen(open);
    };

    const handleSort = (columnId: DocumentSort) => {
        const nextDirection =
            filters.sort === columnId
                ? filters.direction === 'asc'
                    ? 'desc'
                    : 'asc'
                : columnId === 'updated_at'
                  ? 'desc'
                  : 'asc';

        navigateWithFilters({
            sort: columnId,
            direction: nextDirection,
        });
    };

    const handleDelete = (document: KnowledgeDocument) => {
        if (window.confirm('Excluir o documento “' + document.title + '”?')) {
            router.delete(destroy(document.id, { mergeQuery: {} }).url);
        }
    };

    const columns = useMemo<
        ColumnDef<typeof documentTableFeatures, KnowledgeDocument>[]
    >(
        () => [
            {
                accessorKey: 'title',
                header: () => (
                    <SortHeader
                        columnId="title"
                        label="Documento"
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={handleSort}
                    />
                ),
                cell: ({ row }) => (
                    <DocumentTitleCell document={row.original} />
                ),
            },
            {
                accessorKey: 'type',
                header: 'Tipo',
                cell: ({ row }) => (
                    <Badge variant="outline">
                        {documentTypeLabel(row.original.type)}
                    </Badge>
                ),
            },
            {
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <DocumentStatusCell document={row.original} />
                ),
            },
            {
                accessorKey: 'sizeBytes',
                id: 'size',
                header: () => (
                    <SortHeader
                        columnId="size"
                        label="Tamanho"
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={handleSort}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground tabular-nums">
                        {formatBytes(row.original.sizeBytes)}
                    </span>
                ),
            },
            {
                accessorKey: 'updatedAt',
                id: 'updated_at',
                header: () => (
                    <SortHeader
                        columnId="updated_at"
                        label="Atualizado em"
                        sort={filters.sort}
                        direction={filters.direction}
                        onSort={handleSort}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground whitespace-nowrap">
                        {row.original.updatedAt
                            ? new Date(
                                  row.original.updatedAt,
                              ).toLocaleDateString('pt-BR')
                            : 'agora'}
                    </span>
                ),
            },
            ...(permissions.canManageKnowledge
                ? [
                      {
                          id: 'actions',
                          header: '',
                          cell: ({ row }) => (
                              <DocumentActionMenu
                                  document={row.original}
                                  onEdit={openEditDialog}
                                  onMove={setDocumentToMove}
                                  onDelete={handleDelete}
                              />
                          ),
                      } satisfies ColumnDef<
                          typeof documentTableFeatures,
                          KnowledgeDocument
                      >,
                  ]
                : []),
        ],
        [
            filters.direction,
            filters.sort,
            handleDelete,
            handleSort,
            openEditDialog,
            permissions.canManageKnowledge,
        ],
    );

    const table = useTable({
        features: documentTableFeatures,
        columns,
        data: documents.data,
        manualPagination: true,
        manualSorting: true,
        pageCount: documents.last_page,
        state: {
            pagination: {
                pageIndex: documents.current_page - 1,
                pageSize: documents.per_page,
            },
        },
    });

    const hasFilters =
        filters.search !== '' || filters.type !== '' || filters.status !== '';

    return (
        <>
            <ConditionalPoll enabled={polling} />
            <Head title={collection.name + ' · Documentos'} />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="grid gap-3">
                        <Link
                            href={collectionIndex.url()}
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
                                style={{ width: storage.usedPercentage + '%' }}
                            />
                        </div>
                    </CardHeader>
                </Card>

                {documents.total === 0 ? (
                    <Card className="border-dashed shadow-none">
                        <CardContent className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-xl">
                                <FileText className="text-muted-foreground size-6" />
                            </div>
                            <div className="grid gap-1">
                                <h2 className="font-semibold">
                                    {hasFilters
                                        ? 'Nenhum documento encontrado'
                                        : 'Nenhum documento nesta coleção'}
                                </h2>
                                <p className="text-muted-foreground max-w-sm text-sm">
                                    {hasFilters
                                        ? 'Tente ajustar a busca ou remover algum filtro.'
                                        : 'Adicione um documento para começar a alimentar sua base de conhecimento.'}
                                </p>
                            </div>
                            {hasFilters ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        navigateWithFilters({
                                            search: null,
                                            type: null,
                                            status: null,
                                            sort: 'updated_at',
                                            direction: 'desc',
                                        })
                                    }
                                >
                                    Limpar filtros
                                </Button>
                            ) : (
                                permissions.canManageKnowledge && (
                                    <Button
                                        variant="outline"
                                        onClick={openCreateDialog}
                                    >
                                        <Plus />
                                        Criar primeiro documento
                                    </Button>
                                )
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="overflow-hidden shadow-none">
                        <CardHeader className="gap-4">
                            <div className="flex flex-col gap-1">
                                <CardTitle className="text-base">
                                    Documentos
                                </CardTitle>
                                <CardDescription>
                                    {documents.total} documento
                                    {documents.total === 1 ? '' : 's'} nesta
                                    Coleção
                                </CardDescription>
                            </div>
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                                <form
                                    key={filters.search}
                                    className="grid flex-1 gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        const formData = new FormData(
                                            event.currentTarget,
                                        );
                                        navigateWithFilters({
                                            search: String(
                                                formData.get('search') ?? '',
                                            ).trim(),
                                        });
                                    }}
                                >
                                    <Label
                                        htmlFor="document-search"
                                        className="text-muted-foreground text-xs"
                                    >
                                        Buscar por título
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="document-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="Ex.: Política de trocas"
                                        />
                                        <Button type="submit" variant="outline">
                                            Buscar
                                        </Button>
                                    </div>
                                </form>
                                <div className="grid gap-2 sm:grid-cols-2 lg:w-[25rem]">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="document-type-filter"
                                            className="text-muted-foreground text-xs"
                                        >
                                            Tipo
                                        </Label>
                                        <Select
                                            value={filters.type || 'all'}
                                            onValueChange={(value) =>
                                                navigateWithFilters({
                                                    type:
                                                        value === 'all'
                                                            ? null
                                                            : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="document-type-filter">
                                                <SelectValue placeholder="Todos os tipos" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Todos os tipos
                                                </SelectItem>
                                                <SelectItem value="text">
                                                    Texto livre
                                                </SelectItem>
                                                <SelectItem value="faq">
                                                    FAQ
                                                </SelectItem>
                                                <SelectItem value="file">
                                                    Arquivo
                                                </SelectItem>
                                                <SelectItem value="url">
                                                    URL
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="document-status-filter"
                                            className="text-muted-foreground text-xs"
                                        >
                                            Status
                                        </Label>
                                        <Select
                                            value={filters.status || 'all'}
                                            onValueChange={(value) =>
                                                navigateWithFilters({
                                                    status:
                                                        value === 'all'
                                                            ? null
                                                            : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="document-status-filter">
                                                <SelectValue placeholder="Todos os status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Todos os status
                                                </SelectItem>
                                                <SelectItem value="pending">
                                                    Pendente
                                                </SelectItem>
                                                <SelectItem value="processing">
                                                    Processando
                                                </SelectItem>
                                                <SelectItem value="ready">
                                                    Pronto
                                                </SelectItem>
                                                <SelectItem value="failed">
                                                    Erro
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            navigateWithFilters({
                                                search: null,
                                                type: null,
                                                status: null,
                                            })
                                        }
                                    >
                                        Limpar
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        {table
                                            .getHeaderGroups()
                                            .map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map(
                                                        (header) => (
                                                            <TableHead
                                                                key={header.id}
                                                                className={
                                                                    header.id ===
                                                                    'actions'
                                                                        ? 'w-12 text-right'
                                                                        : undefined
                                                                }
                                                            >
                                                                {header.isPlaceholder
                                                                    ? null
                                                                    : table.FlexRender(
                                                                          {
                                                                              header,
                                                                          },
                                                                      )}
                                                            </TableHead>
                                                        ),
                                                    )}
                                                </TableRow>
                                            ))}
                                    </TableHeader>
                                    <TableBody>
                                        {table.getRowModel().rows.map((row) => (
                                            <TableRow key={row.id}>
                                                {row
                                                    .getAllCells()
                                                    .map((cell) => (
                                                        <TableCell
                                                            key={cell.id}
                                                            className={
                                                                cell.column
                                                                    .id ===
                                                                'actions'
                                                                    ? 'text-right'
                                                                    : undefined
                                                            }
                                                        >
                                                            {table.FlexRender({
                                                                cell,
                                                            })}
                                                        </TableCell>
                                                    ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                        <PaginationControls
                            documents={documents}
                            onPageChange={(page) =>
                                navigateWithFilters({ page })
                            }
                        />
                    </Card>
                )}
            </div>
            <DocumentFormDialog
                key={
                    String(documentToEdit?.id ?? 'new') +
                    '-' +
                    (formOpen ? 'open' : 'closed') +
                    '-' +
                    (loadingDocument ? 'loading' : 'ready')
                }
                collectionId={collection.id}
                document={documentToEdit}
                open={formOpen}
                loading={loadingDocument}
                loadError={documentLoadError}
                onOpenChange={handleFormOpenChange}
                onRetry={() => {
                    if (documentToEdit !== null) {
                        openEditDialog(documentToEdit);
                    }
                }}
            />
            <DocumentMoveDialog
                collectionId={collection.id}
                collections={collections}
                document={documentToMove}
                open={documentToMove !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDocumentToMove(null);
                    }
                }}
            />
        </>
    );
}
