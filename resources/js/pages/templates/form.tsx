import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Upload } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import TemplateBasicsStep from '@/components/templates/template-basics-step';
import TemplateContentStep from '@/components/templates/template-content-step';
import {
    buildPayload,
    defaultHeaderType,
    draftFromModel,
    draftIssue,
    formatCatalog,
    formatsForCategory,
    initialDraft,
} from '@/components/templates/template-draft';
import type {
    LibraryModel,
    SavedTemplate,
    TemplateCategory,
    TemplateDraft,
    TemplateFormat,
} from '@/components/templates/template-draft';
import TemplateLibraryPicker from '@/components/templates/template-library-picker';
import TemplateSteps from '@/components/templates/template-steps';
import WhatsappPreview from '@/components/templates/whatsapp-preview';
import { index, show, store, update } from '@/routes/templates';

type Props = {
    template: SavedTemplate | null;
    accounts: { id: number; label: string }[];
    library: LibraryModel[];
};

const basicsFields = [
    'name',
    'whatsapp_account_id',
    'language',
    'requested_category',
    'format',
];

const categoryLabels: Record<TemplateCategory, string> = {
    utility: 'Utilidade',
    marketing: 'Marketing',
    authentication: 'Autenticação',
};

export default function TemplateForm({ template, accounts, library }: Props) {
    const form = useForm<TemplateDraft>(
        initialDraft(template, accounts[0]?.id),
    );
    const [step, setStep] = useState(template ? 2 : 1);
    const draft = form.data;
    const errors = form.errors as Record<string, string>;
    const issue = draftIssue(draft);
    const accountLabel =
        accounts.find((account) => String(account.id) === draft.accountId)
            ?.label ?? '';

    const change = (changes: Partial<TemplateDraft>) =>
        form.setData((current) => ({ ...current, ...changes }));

    const changeFormat = (format: TemplateFormat) =>
        change({
            format,
            headerType: defaultHeaderType(format),
            headerMedia: null,
        });

    const changeCategory = (category: TemplateCategory) => {
        const formats = formatsForCategory(category);
        const format = formats.includes(draft.format)
            ? draft.format
            : formats[0];

        change({
            category,
            format,
            ...(format === draft.format
                ? {}
                : { headerType: defaultHeaderType(format), headerMedia: null }),
        });
    };

    const applyModel = (model: LibraryModel) => {
        change(draftFromModel(draft, model));
        setStep(2);
    };

    const goToContent = () => {
        if (draft.name.trim() !== '') {
            setStep(2);
        }
    };

    const save = (submit: boolean) => {
        form.transform((data) => ({
            ...buildPayload(data, submit),
            ...(template ? { _method: 'put' } : {}),
        }));
        form.post(template ? update.url(template.id) : store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onError: (validationErrors) => {
                if (
                    Object.keys(validationErrors).some((key) =>
                        basicsFields.includes(key),
                    )
                ) {
                    setStep(1);
                }
            },
        });
    };

    const serverErrors = Object.values(errors);

    return (
        <>
            <Head title={template ? 'Editar Template' : 'Criar Template'} />
            <div className="flex h-full min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-4 md:p-6 lg:overflow-hidden">
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2.5 self-start"
                    asChild
                >
                    <Link href={template ? show.url(template.id) : index.url()}>
                        <ArrowLeft />
                        {template ? template.name : 'Templates'}
                    </Link>
                </Button>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="grid gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {template ? 'Editar Template' : 'Criar Template'}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            O rascunho fica salvo até você decidir enviá-lo para
                            a Meta.
                        </p>
                    </div>
                    <TemplateSteps
                        steps={['Formato e dados', 'Conteúdo']}
                        current={step}
                        onSelect={(selected) =>
                            selected === 1 ? setStep(1) : goToContent()
                        }
                    />
                </div>

                <div className="grid flex-1 gap-6 lg:min-h-0 lg:grid-cols-[minmax(0,3fr)_minmax(320px,2fr)]">
                    <section className="bg-card flex flex-col overflow-hidden rounded-xl border lg:min-h-0">
                        {step === 2 && (
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-6 py-4">
                                <div className="grid min-w-0 gap-0.5">
                                    <span className="truncate text-sm font-semibold">
                                        {draft.name}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {[
                                            categoryLabels[draft.category],
                                            formatCatalog[draft.format].label,
                                            draft.language,
                                            accountLabel,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStep(1)}
                                >
                                    Editar dados
                                </Button>
                            </div>
                        )}

                        <div className="flex-1 p-6 lg:min-h-0 lg:overflow-y-auto">
                            {step === 1 ? (
                                <div className="grid gap-8">
                                    {library.length > 0 && (
                                        <TemplateLibraryPicker
                                            models={library}
                                            selectedKey={draft.libraryKey}
                                            onUse={applyModel}
                                        />
                                    )}
                                    <TemplateBasicsStep
                                        draft={draft}
                                        accounts={accounts}
                                        errors={errors}
                                        onChange={change}
                                        onCategoryChange={changeCategory}
                                        onFormatChange={changeFormat}
                                    />
                                </div>
                            ) : (
                                <TemplateContentStep
                                    draft={draft}
                                    errors={errors}
                                    onChange={change}
                                />
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4">
                            {step === 1 ? (
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={
                                            template
                                                ? show.url(template.id)
                                                : index.url()
                                        }
                                    >
                                        Cancelar
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setStep(1)}
                                >
                                    <ArrowLeft /> Voltar
                                </Button>
                            )}
                            <div className="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                                {step === 2 && (issue || serverErrors[0]) && (
                                    <span className="text-destructive text-[13px]">
                                        {serverErrors[0] ?? issue}
                                    </span>
                                )}
                                {step === 1 ? (
                                    <Button
                                        type="button"
                                        disabled={draft.name.trim() === ''}
                                        onClick={goToContent}
                                    >
                                        Continuar <ArrowRight />
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={form.processing}
                                            onClick={() => save(false)}
                                        >
                                            Salvar rascunho
                                        </Button>
                                        <Button
                                            type="button"
                                            disabled={
                                                form.processing ||
                                                issue !== null
                                            }
                                            onClick={() => save(true)}
                                        >
                                            <Upload /> Enviar para a Meta
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="bg-card rounded-xl border p-6 lg:min-h-0 lg:overflow-y-auto">
                        <div className="mb-5 grid gap-1">
                            <h2 className="font-semibold">
                                Pré-visualização no WhatsApp
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Assim o Contato recebe a mensagem, com os
                                exemplos no lugar das variáveis.
                            </p>
                        </div>
                        <WhatsappPreview draft={draft} />
                    </section>
                </div>
            </div>
        </>
    );
}
