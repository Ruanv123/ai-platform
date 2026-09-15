import { Form, Head, Link, router } from '@inertiajs/react';
import { BookOpen, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { destroy, store, update } from '@/routes/collections';
import { index as documentsIndex } from '@/routes/documents';
import type { TeamPermissions } from '@/types';

type KnowledgeCollection = {
    id: number;
    name: string;
    description: string | null;
    isDefault: boolean;
    documentsCount: number;
};

type Props = {
    collections: KnowledgeCollection[];
    permissions: TeamPermissions;
};

type CollectionFormDialogProps = {
    collection: KnowledgeCollection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function CollectionFormDialog({
    collection,
    open,
    onOpenChange,
}: CollectionFormDialogProps) {
    const isEditing = collection !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Editar coleção' : 'Nova coleção'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Atualize o nome e a descrição para manter sua base organizada.'
                            : 'Agrupe documentos por assunto para facilitar a consulta dos Agentes.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={collection?.id ?? 'new'}
                    {...(isEditing ? update.form(collection.id) : store.form())}
                    className="grid gap-5"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="collection-name">Nome</Label>
                                <Input
                                    id="collection-name"
                                    name="name"
                                    defaultValue={collection?.name ?? ''}
                                    placeholder="Ex.: Vendas"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="collection-description">
                                    Descrição
                                </Label>
                                <textarea
                                    id="collection-description"
                                    name="description"
                                    defaultValue={collection?.description ?? ''}
                                    placeholder="Explique que tipo de conhecimento fica aqui."
                                    className="border-input bg-background placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                />
                                <InputError message={errors.description} />
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
                                          ? 'Salvar alterações'
                                          : 'Criar coleção'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

type DeleteCollectionDialogProps = {
    collection: KnowledgeCollection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function DeleteCollectionDialog({
    collection,
    open,
    onOpenChange,
}: DeleteCollectionDialogProps) {
    if (collection === null) {
        return null;
    }

    const handleDelete = () => {
        router.delete(destroy(collection.id).url, {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir coleção?</DialogTitle>
                    <DialogDescription>
                        A coleção <strong>{collection.name}</strong> tem{' '}
                        <strong>
                            {collection.documentsCount}{' '}
                            {collection.documentsCount === 1
                                ? 'documento'
                                : 'documentos'}
                        </strong>{' '}
                        e será excluída junto com eles. Essa ação não pode ser
                        desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={handleDelete}>
                        Excluir coleção
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Collections({ collections, permissions }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [collectionToEdit, setCollectionToEdit] =
        useState<KnowledgeCollection | null>(null);
    const [collectionToDelete, setCollectionToDelete] =
        useState<KnowledgeCollection | null>(null);

    const openCreateDialog = () => {
        setCollectionToEdit(null);
        setFormOpen(true);
    };

    const openEditDialog = (collection: KnowledgeCollection) => {
        setCollectionToEdit(collection);
        setFormOpen(true);
    };

    return (
        <>
            <Head title="Base de conhecimento" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title="Base de conhecimento"
                        description="Organize as informações que seus Agentes podem consultar."
                    />
                    {permissions.canManageKnowledge && (
                        <Button onClick={openCreateDialog}>
                            <Plus />
                            Nova coleção
                        </Button>
                    )}
                </div>

                {collections.length === 0 ? (
                    <Card className="border-dashed shadow-none">
                        <CardContent className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-xl">
                                <BookOpen className="text-muted-foreground size-6" />
                            </div>
                            <div className="grid gap-1">
                                <h2 className="font-semibold">
                                    Nenhuma coleção encontrada
                                </h2>
                                <p className="text-muted-foreground max-w-sm text-sm">
                                    Crie uma coleção para começar a organizar o
                                    conhecimento da sua Empresa.
                                </p>
                            </div>
                            {permissions.canManageKnowledge && (
                                <Button
                                    variant="outline"
                                    onClick={openCreateDialog}
                                >
                                    <Plus />
                                    Criar primeira coleção
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {collections.map((collection) => (
                            <Card
                                key={collection.id}
                                className="group relative overflow-hidden shadow-none transition-shadow hover:shadow-sm"
                            >
                                <CardHeader className="gap-3">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="bg-muted flex size-10 shrink-0 items-center justify-center rounded-lg">
                                            <BookOpen className="text-muted-foreground size-5" />
                                        </div>
                                        {collection.isDefault && (
                                            <Badge variant="secondary">
                                                Padrão
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="grid gap-1">
                                        <CardTitle className="text-base">
                                            {collection.name}
                                        </CardTitle>
                                        <CardDescription className="min-h-10 text-sm leading-5">
                                            {collection.description ||
                                                'Sem descrição cadastrada.'}
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between gap-3">
                                    <div className="text-muted-foreground flex items-center gap-2 text-sm">
                                        <FileText className="size-4" />
                                        <span>
                                            {collection.documentsCount}{' '}
                                            {collection.documentsCount === 1
                                                ? 'documento'
                                                : 'documentos'}
                                        </span>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={documentsIndex.url(collection.id)}>
                                            Abrir documentos
                                        </Link>
                                    </Button>
                                    {permissions.canManageKnowledge && (
                                        <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    openEditDialog(collection)
                                                }
                                                aria-label={`Editar ${collection.name}`}
                                            >
                                                <Pencil />
                                            </Button>
                                            {!collection.isDefault && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setCollectionToDelete(
                                                            collection,
                                                        )
                                                    }
                                                    aria-label={`Excluir ${collection.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <CollectionFormDialog
                collection={collectionToEdit}
                open={formOpen}
                onOpenChange={setFormOpen}
            />
            <DeleteCollectionDialog
                collection={collectionToDelete}
                open={collectionToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCollectionToDelete(null);
                    }
                }}
            />
        </>
    );
}
