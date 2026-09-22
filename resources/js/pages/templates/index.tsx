import { Form, Head, Link, router } from '@inertiajs/react';
import { Check, Plus, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { index, show, store, sync } from '@/routes/templates';

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

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: [string, string][];
}) {
    return (
        <div className="grid min-w-40 gap-2">
            <Label>{label}</Label>
            <select
                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
        </div>
    );
}

function CreateTemplateDialog({
    open,
    onOpenChange,
    accounts,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accounts: { id: number; label: string }[];
}) {
    const [body, setBody] = useState('');
    const [headerText, setHeaderText] = useState('');
    const [footer, setFooter] = useState('');
    const [buttonTexts, setButtonTexts] = useState(['', '', '']);
    const preview = body.replace(
        /{{\s*(\d+)\s*}}/g,
        (_match, position: string) => `valor ${position}`,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Criar Template</DialogTitle>
                    <DialogDescription>
                        O rascunho fica salvo até você decidir enviá-lo para a
                        Meta.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={store.url()}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="account">
                                    Conta do WhatsApp
                                </Label>
                                <select
                                    id="account"
                                    name="whatsapp_account_id"
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    defaultValue={accounts[0]?.id ?? ''}
                                >
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome técnico</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="lembrete_consulta"
                                />
                                {errors.name && (
                                    <p className="text-destructive text-xs">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="language">Idioma</Label>
                                <Input
                                    id="language"
                                    name="language"
                                    defaultValue="pt_BR"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category">
                                    Categoria pedida
                                </Label>
                                <select
                                    id="category"
                                    name="requested_category"
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    defaultValue="utility"
                                >
                                    <option value="utility">Utilidade</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="authentication">
                                        Autenticação
                                    </option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="body">Corpo</Label>
                                <textarea
                                    id="body"
                                    name="body"
                                    value={body}
                                    onChange={(event) =>
                                        setBody(event.target.value)
                                    }
                                    className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    placeholder="Olá {{1}}, seu agendamento é amanhã."
                                />
                                {errors.body && (
                                    <p className="text-destructive text-xs">
                                        {errors.body}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="header_text">
                                    Cabeçalho (opcional)
                                </Label>
                                <Input
                                    id="header_text"
                                    name="header_text"
                                    value={headerText}
                                    onChange={(event) =>
                                        setHeaderText(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="footer">
                                    Rodapé (opcional)
                                </Label>
                                <Input
                                    id="footer"
                                    name="footer"
                                    value={footer}
                                    onChange={(event) =>
                                        setFooter(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>
                                    Botões de resposta rápida (opcional)
                                </Label>
                                {buttonTexts.map((text, index) => (
                                    <Input
                                        key={index}
                                        id={`button-${index}`}
                                        name={
                                            text.trim() === ''
                                                ? undefined
                                                : `buttons[${index}][text]`
                                        }
                                        value={text}
                                        placeholder={`Botão ${index + 1}`}
                                        onChange={(event) => {
                                            const next = [...buttonTexts];
                                            next[index] = event.target.value;
                                            setButtonTexts(next);
                                        }}
                                    />
                                ))}
                                {buttonTexts.map(
                                    (text, index) =>
                                        text.trim() !== '' && (
                                            <input
                                                key={`button-type-${index}`}
                                                type="hidden"
                                                name={`buttons[${index}][type]`}
                                                value="QUICK_REPLY"
                                            />
                                        ),
                                )}
                            </div>
                            {(body || headerText || footer) && (
                                <div className="bg-muted/50 grid gap-1 rounded-lg border p-3">
                                    <span className="text-muted-foreground text-xs font-medium">
                                        Prévia
                                    </span>
                                    {headerText && (
                                        <p className="text-sm font-semibold">
                                            {headerText}
                                        </p>
                                    )}
                                    <p className="text-sm leading-5 whitespace-pre-wrap">
                                        {preview || 'O corpo aparecerá aqui.'}
                                    </p>
                                    {footer && (
                                        <p className="text-muted-foreground text-xs">
                                            {footer}
                                        </p>
                                    )}
                                </div>
                            )}
                            {errors.template && (
                                <p className="text-destructive text-sm">
                                    {errors.template}
                                </p>
                            )}
                            <Button type="submit" disabled={processing}>
                                <Check /> Salvar rascunho
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
