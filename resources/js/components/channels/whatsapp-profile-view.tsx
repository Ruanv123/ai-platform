import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    WhatsAppProfileCapabilities,
    WhatsAppProfileCategoryOption,
    WhatsAppProfileField,
    WhatsAppProfileResult,
} from '@/components/channels/whatsapp-profile-types';
import WhatsAppProfileTextForm from '@/components/channels/whatsapp-profile-text-form';

type Props = {
    profile: WhatsAppProfileResult;
    capabilities: WhatsAppProfileCapabilities;
    connectionUrl: string;
    channelId: string | null;
    canEdit: boolean;
    categories: WhatsAppProfileCategoryOption[];
};

const nameStatusLabels: Record<string, string> = {
    APPROVED: 'Aprovado',
    DECLINED: 'Recusado',
    EXPIRED: 'Expirado',
    PENDING: 'Em revisão',
};

function formatNameStatus(status: string | null): string | null {
    if (status === null) {
        return null;
    }

    return nameStatusLabels[status] ?? status;
}

export default function WhatsAppProfileView({
    profile,
    capabilities,
    connectionUrl,
    channelId,
    canEdit,
    categories,
}: Props) {
    if (profile.state === 'not_connected') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Perfil do WhatsApp
                    </CardTitle>
                    <CardDescription>
                        Conecte o Canal para consultar como a Empresa aparece no
                        WhatsApp.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Link
                        href={connectionUrl}
                        className="text-primary text-sm font-medium underline-offset-4 hover:underline"
                    >
                        Ir para Conexão
                    </Link>
                </CardContent>
            </Card>
        );
    }

    if (profile.state === 'error') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Não foi possível carregar o Perfil
                    </CardTitle>
                    <CardDescription role="alert">
                        {profile.message}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.reload({ only: ['whatsappProfile'] })}
                    >
                        <RefreshCw />
                        Tentar de novo
                    </Button>
                </CardContent>
            </Card>
        );
    }

    const data = profile.data;
    const editableTextFields = capabilities.editableFields.filter((field) =>
        ['about', 'description', 'address', 'email', 'websites', 'category'].includes(field),
    );
    const allFacts: {
        field: WhatsAppProfileField;
        label: string;
        value: string | null;
    }[] = [
        {
            field: 'displayName',
            label: 'Nome de exibição',
            value: data.displayName,
        },
        { field: 'about', label: 'Recado', value: data.about },
        { field: 'description', label: 'Descrição', value: data.description },
        { field: 'address', label: 'Endereço', value: data.address },
        { field: 'email', label: 'E-mail', value: data.email },
        {
            field: 'websites',
            label: 'Sites',
            value: data.websites.length > 0 ? data.websites.join(', ') : null,
        },
        { field: 'category', label: 'Categoria', value: data.category },
    ];
    const facts = allFacts.filter((fact) =>
        capabilities.readableFields.includes(fact.field) &&
        !editableTextFields.includes(fact.field),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Perfil do WhatsApp</CardTitle>
                <CardDescription>
                    Dados atuais do número, consultados diretamente no provedor.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                {capabilities.readableFields.includes('profilePicture') && (
                    <div className="flex items-center gap-4">
                                {data.profilePictureUrl ? (
                            <img
                                src={data.profilePictureUrl}
                                alt="Foto do Perfil do WhatsApp"
                                className="size-20 rounded-full object-cover"
                            />
                        ) : (
                            <div
                                aria-label="Foto do Perfil indisponível"
                                className="bg-muted size-20 rounded-full"
                            />
                        )}
                    </div>
                )}
                <dl className="grid gap-4 md:grid-cols-2">
                    {facts.map((fact) => (
                        <div key={fact.field} className="grid content-start gap-1">
                            <dt className="text-muted-foreground text-sm">
                                {fact.label}
                            </dt>
                            <dd className="break-words text-sm font-medium">
                                {fact.value ?? '—'}
                            </dd>
                                {fact.field === 'displayName' &&
                                    formatNameStatus(data.nameStatus) && (
                                        <dd className="text-muted-foreground text-sm">
                                            {formatNameStatus(data.nameStatus)}
                                        </dd>
                                    )}
                            {fact.field === 'displayName' &&
                                data.newDisplayName && (
                                    <dd className="text-muted-foreground text-sm">
                                        Pedido: {data.newDisplayName}
                                        {formatNameStatus(
                                            data.newNameStatus,
                                        ) &&
                                            ` · ${formatNameStatus(data.newNameStatus)}`}
                                    </dd>
                                )}
                        </div>
                    ))}
                </dl>
                {channelId !== null && editableTextFields.length > 0 && (
                    <WhatsAppProfileTextForm
                        key={JSON.stringify(data)}
                        channelId={channelId}
                        profile={data}
                        editableFields={editableTextFields}
                        canEdit={canEdit}
                        categories={categories}
                    />
                )}
            </CardContent>
        </Card>
    );
}
