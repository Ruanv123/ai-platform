import { Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ChoiceCard from './choice-card';
import {
    catalogOnlyFormats,
    categoryOptions,
    formatCatalog,
    formatsForCategory,
} from './template-draft';
import type {
    TemplateCategory,
    TemplateDraft,
    TemplateFormat,
} from './template-draft';

export default function TemplateBasicsStep({
    draft,
    accounts,
    errors,
    onChange,
    onCategoryChange,
    onFormatChange,
}: {
    draft: TemplateDraft;
    accounts: { id: number; label: string }[];
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
    onCategoryChange: (category: TemplateCategory) => void;
    onFormatChange: (format: TemplateFormat) => void;
}) {
    return (
        <div className="grid content-start gap-6">
            <div className="grid gap-1">
                <h2 className="font-semibold">Formato e dados</h2>
                <p className="text-muted-foreground text-sm">
                    Como a Meta vai identificar, avaliar e exibir este Template.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nome técnico</Label>
                <Input
                    id="name"
                    value={draft.name}
                    onChange={(event) =>
                        onChange({
                            name: event.target.value
                                .toLowerCase()
                                .replace(/\s+/g, '_')
                                .replace(/[^a-z0-9_]/g, ''),
                        })
                    }
                    placeholder="lembrete_consulta"
                />
                <p className="text-muted-foreground text-xs">
                    Só letras minúsculas, números e _. Espaços viram _
                    automaticamente.
                </p>
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="account">Conta do WhatsApp</Label>
                    <select
                        id="account"
                        value={draft.accountId}
                        onChange={(event) =>
                            onChange({ accountId: event.target.value })
                        }
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        {accounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.whatsapp_account_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="language">Idioma</Label>
                    <select
                        id="language"
                        value={draft.language}
                        onChange={(event) =>
                            onChange({ language: event.target.value })
                        }
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option value="pt_BR">Português (Brasil)</option>
                        <option value="en_US">Inglês (EUA)</option>
                        <option value="es">Espanhol</option>
                    </select>
                    <InputError message={errors.language} />
                </div>
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm leading-none font-medium">
                    Categoria
                </legend>
                <div className="grid gap-3 sm:grid-cols-3">
                    {categoryOptions.map((option) => (
                        <ChoiceCard
                            key={option.value}
                            name="category"
                            value={option.value}
                            checked={draft.category === option.value}
                            label={option.label}
                            description={option.description}
                            onSelect={() => onCategoryChange(option.value)}
                        />
                    ))}
                </div>
                <p className="text-muted-foreground text-xs">
                    A Meta pode atribuir outra categoria durante a análise.
                </p>
                <InputError message={errors.requested_category} />
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm leading-none font-medium">
                    Formato
                </legend>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {formatsForCategory(draft.category).map((format) => (
                        <ChoiceCard
                            key={format}
                            name="format"
                            value={format}
                            checked={draft.format === format}
                            label={formatCatalog[format].label}
                            description={formatCatalog[format].description}
                            onSelect={() => onFormatChange(format)}
                        />
                    ))}
                </div>
                {draft.category === 'marketing' && (
                    <div className="grid gap-2 rounded-lg border border-dashed p-3">
                        <span className="text-muted-foreground text-[13px]">
                            Precisam de um catálogo de produtos conectado à
                            conta do WhatsApp
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {catalogOnlyFormats.map((label) => (
                                <span
                                    key={label}
                                    className="bg-muted text-muted-foreground inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[13px]"
                                >
                                    <Lock className="size-3" />
                                    {label}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
                <InputError message={errors.format} />
            </fieldset>
        </div>
    );
}
