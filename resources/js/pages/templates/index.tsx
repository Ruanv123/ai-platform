import { Head, Link, router } from '@inertiajs/react';
import { Plus, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import CreateTemplateDialog from '@/components/templates/create-template-dialog';
import FilterSelect from '@/components/templates/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show, sync } from '@/routes/templates';

type Template = {
    id: number;
    name: string;
    language: string;
    status: string;
    statusLabel: string;
    requestedCategoryLabel: string;
    assignedCategoryLabel: string | null;
    accountLabel: string;
    showAccount: boolean;
};

type Props = {
    templates: {
        data: Template[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    accounts: { id: number; label: string }[];
    filters: {
        status: string | null;
        category: string | null;
        search: string;
        account: number | null;
    };
    multipleAccounts: boolean;
    permissions: { canManage: boolean };
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    approved: 'secondary',
    rejected: 'destructive',
    pending: 'outline',
    deleted: 'destructive',
    draft: 'outline',
    paused: 'outline',
    disabled: 'destructive',
};

export default function TemplatesIndex({
    templates,
    accounts,
    filters,
    multipleAccounts,
    permissions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<typeof filters>) => {
        router.get(
            index.url({ query: { ...filters, ...changes, page: 1 } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Templates" />
            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Templates"
                        description="Acompanhe os Templates aprovados e o que a Meta decidiu sobre cada um."
                    />
                    <div className="flex flex-wrap gap-2">
                        {permissions.canManage && (
                            <Button
                                variant="outline"
                                onClick={() => router.post(sync.url())}
                            >
                                <RefreshCw /> Sincronizar Meta
                            </Button>
                        )}
                        {permissions.canManage && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus /> Criar Template
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid min-w-56 gap-2">
                        <Label htmlFor="template-search">Buscar</Label>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                            <Input
                                id="template-search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters({ search });
                                    }
                                }}
                                className="pl-9"
                                placeholder="Nome do Template"
                            />
                        </div>
                    </div>
                    <FilterSelect
                        label="Status"
                        value={filters.status ?? ''}
                        onChange={(value) =>
                            applyFilters({ status: value || null })
                        }
                        options={[
                            ['', 'Todos'],
                            ['approved', 'Aprovado'],
                            ['pending', 'Aguardando aprovação'],
                            ['rejected', 'Recusado'],
                            ['draft', 'Rascunho'],
                            ['deleted', 'Removido na Meta'],
                        ]}
                    />
                    <FilterSelect
                        label="Categoria"
                        value={filters.category ?? ''}
                        onChange={(value) =>
                            applyFilters({ category: value || null })
                        }
                        options={[
                            ['', 'Todas'],
                            ['utility', 'Utilidade'],
                            ['marketing', 'Marketing'],
                            ['authentication', 'Autenticação'],
                        ]}
                    />
                    {multipleAccounts && (
                        <FilterSelect
                            label="Conta"
                            value={filters.account?.toString() ?? ''}
                            onChange={(value) =>
                                applyFilters({
                                    account: value ? Number(value) : null,
                                })
                            }
                            options={[
                                ['', 'Todas'],
                                ...accounts.map(
                                    (account) =>
                                        [String(account.id), account.label] as [
                                            string,
                                            string,
                                        ],
                                ),
                            ]}
                        />
                    )}
                </div>

                <div className="bg-card overflow-hidden rounded-xl border">
                    {templates.data.length === 0 ? (
                        <div className="text-muted-foreground px-6 py-16 text-center text-sm">
                            Nenhum Template encontrado. Sincronize com a Meta ou
                            crie um rascunho.
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Template</TableHead>
                                    {multipleAccounts && (
                                        <TableHead>Conta</TableHead>
                                    )}
                                    <TableHead>Categoria</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {templates.data.map((template) => (
                                    <TableRow key={template.id}>
                                        <TableCell>
                                            <Link
                                                href={show.url(template.id)}
                                                className="grid gap-0.5 font-medium hover:underline"
                                            >
                                                <span>{template.name}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {template.language}
                                                </span>
                                            </Link>
                                        </TableCell>
                                        {multipleAccounts && (
                                            <TableCell className="text-muted-foreground text-sm">
                                                {template.accountLabel}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <div className="grid gap-1 text-sm">
                                                <span>
                                                    {template.assignedCategoryLabel ??
                                                        template.requestedCategoryLabel}
                                                </span>
                                                {template.assignedCategoryLabel !==
                                                    null &&
                                                    template.assignedCategoryLabel !==
                                                        template.requestedCategoryLabel && (
                                                        <span className="text-signal-attention text-xs">
                                                            Meta atribuiu outra
                                                            categoria
                                                        </span>
                                                    )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        template.status
                                                    ] ?? 'outline'
                                                }
                                            >
                                                {template.statusLabel}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                {templates.lastPage > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {templates.total} Templates
                        </span>
                        <div className="flex gap-2">
                            {templates.currentPage > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        applyFilters({
                                            page: templates.currentPage - 1,
                                        } as never)
                                    }
                                >
                                    Anterior
                                </Button>
                            )}
                            {templates.currentPage < templates.lastPage && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        applyFilters({
                                            page: templates.currentPage + 1,
                                        } as never)
                                    }
                                >
                                    Próxima
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
            <CreateTemplateDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                accounts={accounts}
            />
        </>
    );
}
