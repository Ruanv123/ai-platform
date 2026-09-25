import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    WhatsAppProfileCategoryOption,
    WhatsAppProfileData,
    WhatsAppProfileField,
} from '@/components/channels/whatsapp-profile-types';

type FormData = {
    about: string;
    description: string;
    address: string;
    email: string;
    websites: string[];
    category: string;
};

type Props = {
    updateTextsUrl: string;
    profile: WhatsAppProfileData;
    editableFields: WhatsAppProfileField[];
    canEdit: boolean;
    categories: WhatsAppProfileCategoryOption[];
};

function normalizedWebsites(values: string[]): string[] {
    return values.map((value) => value.trim()).filter((value) => value !== '');
}

function changedFields(
    data: FormData,
    initialData: FormData,
    editableFields: WhatsAppProfileField[],
    initialWebsites: string[],
) {
    const payload: Record<string, string | string[]> = {};

    for (const field of ['about', 'description', 'address', 'email', 'category'] as const) {
        if (editableFields.includes(field) && data[field] !== initialData[field]) {
            payload[field] = data[field];
        }
    }

    const websites = normalizedWebsites(data.websites);

    if (
        editableFields.includes('websites') &&
        JSON.stringify(websites) !== JSON.stringify(initialWebsites)
    ) {
        payload.websites = websites;
    }

    return payload;
}

export default function WhatsAppProfileTextForm({
    updateTextsUrl,
    profile,
    editableFields,
    canEdit,
    categories,
}: Props) {
    const initialWebsites = normalizedWebsites(profile.websites);
    const initialData: FormData = {
        about: profile.about ?? '',
        description: profile.description ?? '',
        address: profile.address ?? '',
        email: profile.email ?? '',
        websites: [initialWebsites[0] ?? '', initialWebsites[1] ?? ''],
        category: profile.category ?? '',
    };
    const form = useForm<FormData>(initialData);
    const updates = changedFields(
        form.data,
        initialData,
        editableFields,
        initialWebsites,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!canEdit || Object.keys(updates).length === 0) {
            return;
        }

        form.transform(() => updates);
        form.post(updateTextsUrl, {
            preserveScroll: true,
        });
    }

    const canEditAbout = editableFields.includes('about');
    const canEditDescription = editableFields.includes('description');
    const canEditAddress = editableFields.includes('address');
    const canEditEmail = editableFields.includes('email');
    const canEditWebsites = editableFields.includes('websites');
    const canEditCategory = editableFields.includes('category');

    return (
        <form className="grid gap-5" onSubmit={submit}>
            {canEditAbout && (
                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="profile-about">Recado</Label>
                        <span className="text-muted-foreground text-xs">
                            {form.data.about.length}/139
                        </span>
                    </div>
                    <textarea
                        id="profile-about"
                        value={form.data.about}
                        onChange={(event) => form.setData('about', event.target.value)}
                        maxLength={139}
                        disabled={!canEdit}
                        aria-invalid={Boolean(form.errors.about)}
                        className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError message={form.errors.about} />
                </div>
            )}

            {canEditDescription && (
                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="profile-description">Descrição</Label>
                        <span className="text-muted-foreground text-xs">
                            {form.data.description.length}/512
                        </span>
                    </div>
                    <textarea
                        id="profile-description"
                        value={form.data.description}
                        onChange={(event) => form.setData('description', event.target.value)}
                        maxLength={512}
                        disabled={!canEdit}
                        aria-invalid={Boolean(form.errors.description)}
                        className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError message={form.errors.description} />
                </div>
            )}

            {canEditAddress && (
                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="profile-address">Endereço</Label>
                        <span className="text-muted-foreground text-xs">
                            {form.data.address.length}/256
                        </span>
                    </div>
                    <Input
                        id="profile-address"
                        value={form.data.address}
                        onChange={(event) => form.setData('address', event.target.value)}
                        maxLength={256}
                        disabled={!canEdit}
                        aria-invalid={Boolean(form.errors.address)}
                    />
                    <InputError message={form.errors.address} />
                </div>
            )}

            {canEditEmail && (
                <div className="grid gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <Label htmlFor="profile-email">E-mail</Label>
                        <span className="text-muted-foreground text-xs">
                            {form.data.email.length}/128
                        </span>
                    </div>
                    <Input
                        id="profile-email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        maxLength={128}
                        disabled={!canEdit}
                        aria-invalid={Boolean(form.errors.email)}
                    />
                    <InputError message={form.errors.email} />
                </div>
            )}

            {canEditWebsites && (
                <div className="grid gap-3">
                    <Label>Sites</Label>
                    {[0, 1].map((index) => {
                        const value = form.data.websites[index] ?? '';
                        const error = form.errors[`websites.${index}`];
                        const websitesError = (
                            form.errors as Record<string, string | undefined>
                        ).websites;

                        return (
                            <div key={index} className="grid gap-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label htmlFor={`profile-website-${index + 1}`}>
                                        Site {index + 1}
                                    </Label>
                                    <span className="text-muted-foreground text-xs">
                                        {value.length}/256
                                    </span>
                                </div>
                                <Input
                                    id={`profile-website-${index + 1}`}
                                    type="url"
                                    value={value}
                                    onChange={(event) =>
                                        form.setData(
                                            'websites',
                                            form.data.websites.map((website, websiteIndex) =>
                                                websiteIndex === index
                                                    ? event.target.value
                                                    : website,
                                            ),
                                        )
                                    }
                                    maxLength={256}
                                    disabled={!canEdit}
                                    aria-invalid={Boolean(error || websitesError)}
                                />
                                <InputError message={error ?? websitesError} />
                            </div>
                        );
                    })}
                </div>
            )}

            {canEditCategory && (
                <div className="grid gap-2">
                    <Label htmlFor="profile-category">Categoria</Label>
                    <Select
                        value={form.data.category || undefined}
                        onValueChange={(value) => form.setData('category', value)}
                        disabled={!canEdit}
                    >
                        <SelectTrigger id="profile-category" aria-invalid={Boolean(form.errors.category)}>
                            <SelectValue placeholder="Selecione uma categoria" />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem key={category.value} value={category.value}>
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.category} />
                </div>
            )}

            {canEdit && (
                <div>
                    <Button type="submit" disabled={form.processing || Object.keys(updates).length === 0}>
                        {form.processing ? 'Salvando…' : 'Salvar'}
                    </Button>
                </div>
            )}
        </form>
    );
}
