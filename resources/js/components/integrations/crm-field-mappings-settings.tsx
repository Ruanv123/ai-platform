import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import fieldMappings from '@/routes/integrations/crm/field-mappings';

type ContactFieldOption = {
    id: string;
    name: string;
    type: string;
    options: string[];
    sensitive: boolean;
};

type CrmFieldOption = {
    id: string;
    name: string;
    type: string;
    options: string[];
};

type FieldMapping = {
    id: string;
    contactFieldId: string;
    crmFieldId: string;
    direction: 'to_crm' | 'from_crm';
    optionMappings: Record<string, string>;
};

type MappingFormData = {
    contact_field_id: string;
    crm_field_id: string;
    direction: 'to_crm' | 'from_crm';
    option_mappings: Record<string, string>;
};

type Props = {
    contactFields: ContactFieldOption[];
    crmFields: CrmFieldOption[];
    mappings: FieldMapping[];
};

export default function CrmFieldMappingsSettings({
    contactFields,
    crmFields,
    mappings,
}: Props) {
    const [editingId, setEditingId] = useState<string | null>(null);
    const form = useForm<MappingFormData>({
        contact_field_id: '',
        crm_field_id: '',
        direction: 'to_crm',
        option_mappings: {},
    });
    const selectedContactField = contactFields.find(
        (field) => field.id === form.data.contact_field_id,
    );
    const compatibleCrmFields = crmFields.filter((field) =>
        isCompatible(selectedContactField?.type, field.type),
    );
    const selectedCrmField = compatibleCrmFields.find(
        (field) => field.id === form.data.crm_field_id,
    );
    const needsOptionMappings =
        selectedContactField?.type === 'choice' &&
        selectedCrmField !== undefined;
    const hasCompleteOptionMappings =
        !needsOptionMappings ||
        (selectedContactField.options.length > 0 &&
            selectedContactField.options.every((option) =>
                Boolean(form.data.option_mappings[option]),
            ) &&
            new Set(Object.values(form.data.option_mappings)).size ===
                selectedContactField.options.length);

    const resetForm = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
    };

    const submit = () => {
        form.transform((data) => ({
            ...data,
            option_mappings: needsOptionMappings
                ? data.option_mappings
                : {},
        }));
        const options = {
            preserveScroll: true,
            onSuccess: resetForm,
        };

        if (editingId) {
            form.put(fieldMappings.update(editingId).url, options);

            return;
        }

        form.post(fieldMappings.store().url, options);
    };

    const startEditing = (mapping: FieldMapping) => {
        setEditingId(mapping.id);
        form.setData({
            contact_field_id: mapping.contactFieldId,
            crm_field_id: mapping.crmFieldId,
            direction: mapping.direction,
            option_mappings: mapping.optionMappings,
        });
        form.clearErrors();
    };

    const removeMapping = (mappingId: string) => {
        form.delete(fieldMappings.destroy(mappingId).url, {
            preserveScroll: true,
            onSuccess: () => {
                if (editingId === mappingId) {
                    resetForm();
                }
            },
        });
    };

    return (
        <Card className="max-w-3xl">
            <CardHeader>
                <CardTitle>Mapeamento de campos</CardTitle>
                <CardDescription>
                    Sincronize campos da plataforma com campos personalizados
                    da Pessoa no RD Station em uma única direção.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-5">
                {mappings.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nenhum campo está mapeado.
                    </p>
                ) : (
                    <div className="grid gap-2">
                        {mappings.map((mapping) => {
                            const contactField = contactFields.find(
                                (field) => field.id === mapping.contactFieldId,
                            );
                            const crmField = crmFields.find(
                                (field) => field.id === mapping.crmFieldId,
                            );

                            return (
                                <div
                                    key={mapping.id}
                                    className="border-border flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                >
                                    <div className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            {contactField?.name ?? 'Campo removido'}
                                            {' → '}
                                            {crmField?.name ?? 'Campo removido'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {mapping.direction === 'to_crm'
                                                ? 'Plataforma para o CRM'
                                                : 'CRM para a plataforma'}
                                            {contactField?.sensitive
                                                ? ' · valor sensível mascarado nas operações'
                                                : ''}
                                        </span>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => startEditing(mapping)}
                                        >
                                            <Pencil /> Editar
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            aria-label={`Remover mapeamento de ${contactField?.name ?? 'campo'}`}
                                            onClick={() => removeMapping(mapping.id)}
                                        >
                                            <Trash2 /> Remover
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <div className="border-border grid gap-4 rounded-md border p-4">
                    <div className="grid gap-1">
                        <h3 className="text-sm font-semibold">
                            {editingId ? 'Editar mapeamento' : 'Novo mapeamento'}
                        </h3>
                        <p className="text-muted-foreground text-sm">
                            Cada campo pode participar de um único mapeamento.
                        </p>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Campo do Contato
                            </label>
                            <Select
                                value={form.data.contact_field_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData((current) => ({
                                        ...current,
                                        contact_field_id:
                                            value === 'none' ? '' : value,
                                        crm_field_id: '',
                                        option_mappings: {},
                                    }))
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Selecione um campo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Selecione um campo
                                    </SelectItem>
                                    {contactFields.map((field) => (
                                        <SelectItem key={field.id} value={field.id}>
                                            {field.name} · {field.type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.contact_field_id} />
                        </div>
                        <div className="grid gap-2">
                            <label className="text-sm font-medium">
                                Campo da Pessoa no CRM
                            </label>
                            <Select
                                value={form.data.crm_field_id || 'none'}
                                disabled={!selectedContactField}
                                onValueChange={(value) =>
                                    form.setData((current) => ({
                                        ...current,
                                        crm_field_id:
                                            value === 'none' ? '' : value,
                                        option_mappings: {},
                                    }))
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Selecione um campo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Selecione um campo compatível
                                    </SelectItem>
                                    {compatibleCrmFields.map((field) => (
                                        <SelectItem key={field.id} value={field.id}>
                                            {field.name} · {field.type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.crm_field_id} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">Direção</label>
                        <Select
                            value={form.data.direction}
                            onValueChange={(value: 'to_crm' | 'from_crm') =>
                                form.setData('direction', value)
                            }
                        >
                            <SelectTrigger className="w-full md:max-w-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="to_crm">
                                    Plataforma para o CRM
                                </SelectItem>
                                <SelectItem value="from_crm">
                                    CRM para a plataforma
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.direction} />
                    </div>
                    {needsOptionMappings && (
                        <div className="grid gap-3">
                            <div>
                                <h4 className="text-sm font-medium">
                                    Correspondência das opções
                                </h4>
                                <p className="text-muted-foreground text-sm">
                                    Mapeie cada opção local para uma opção diferente
                                    do CRM.
                                </p>
                            </div>
                            {selectedContactField.options.map((option) => {
                                const selectedValue =
                                    form.data.option_mappings[option] ?? '';

                                return (
                                    <div
                                        key={option}
                                        className="grid items-center gap-2 sm:grid-cols-2"
                                    >
                                        <span className="text-sm">{option}</span>
                                        <Select
                                            value={selectedValue || 'unmapped'}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'option_mappings',
                                                    value === 'unmapped'
                                                        ? omitOptionMapping(
                                                              form.data.option_mappings,
                                                              option,
                                                          )
                                                        : {
                                                              ...form.data
                                                                  .option_mappings,
                                                              [option]: value,
                                                          },
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Selecione uma opção" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="unmapped">
                                                    Selecione uma opção
                                                </SelectItem>
                                                {selectedCrmField.options.map(
                                                    (crmOption) => (
                                                        <SelectItem
                                                            key={crmOption}
                                                            value={crmOption}
                                                            disabled={
                                                                Object.entries(
                                                                    form.data
                                                                        .option_mappings,
                                                                ).some(
                                                                    ([localOption, value]) =>
                                                                        localOption !==
                                                                            option &&
                                                                        value ===
                                                                            crmOption,
                                                                )
                                                            }
                                                        >
                                                            {crmOption}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                );
                            })}
                            <InputError message={form.errors.option_mappings} />
                        </div>
                    )}
                    {selectedContactField &&
                        compatibleCrmFields.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                Não há campos do CRM com tipo compatível.
                            </p>
                        )}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            disabled={
                                form.processing ||
                                !form.data.contact_field_id ||
                                !form.data.crm_field_id ||
                                !hasCompleteOptionMappings
                            }
                            onClick={submit}
                        >
                            <Plus />
                            {editingId ? 'Salvar alterações' : 'Adicionar mapeamento'}
                        </Button>
                        {editingId && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={resetForm}
                            >
                                <X /> Cancelar edição
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function isCompatible(localType: string | undefined, crmType: string): boolean {
    if (localType === 'choice') {
        return crmType === 'option' || crmType === 'select';
    }

    return localType === crmType && ['text', 'number', 'date'].includes(crmType);
}

function omitOptionMapping(
    mappings: Record<string, string>,
    option: string,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(mappings).filter(([localOption]) => localOption !== option),
    );
}
