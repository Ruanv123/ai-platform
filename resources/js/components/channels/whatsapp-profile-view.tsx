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
import WhatsAppProfilePhotoBlock from '@/components/channels/whatsapp-profile-photo-block';
import WhatsAppProfileDisplayNameBlock from '@/components/channels/whatsapp-profile-display-name-block';
import WhatsAppProfileTextForm from '@/components/channels/whatsapp-profile-text-form';

type Props = {
    profile: WhatsAppProfileResult;
    capabilities: WhatsAppProfileCapabilities;
    connectionUrl: string;
    channelId: string | null;
    updateTextsUrl: string | null;
    updateProfilePictureUrl: string | null;
    requestDisplayNameUrl: string | null;
    appId: string | null;
    requiresAppId: boolean;
    canEdit: boolean;
    categories: WhatsAppProfileCategoryOption[];
};

export default function WhatsAppProfileView({
    profile,
    capabilities,
    connectionUrl,
    channelId,
    updateTextsUrl,
    updateProfilePictureUrl,
    requestDisplayNameUrl,
    appId,
    requiresAppId,
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
                    <WhatsAppProfilePhotoBlock
                        profilePictureUrl={data.profilePictureUrl}
                        appId={appId}
                        requiresAppId={requiresAppId}
                        connectionUrl={connectionUrl}
                        updateProfilePictureUrl={updateProfilePictureUrl}
                        canEdit={
                            canEdit &&
                            capabilities.editableFields.includes('profilePicture')
                        }
                    />
                )}
                {capabilities.readableFields.includes('displayName') && (
                    <WhatsAppProfileDisplayNameBlock
                        profile={data}
                        requestDisplayNameUrl={requestDisplayNameUrl}
                        canRequestDisplayName={capabilities.canRequestDisplayName}
                        canEdit={canEdit}
                    />
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
                        </div>
                    ))}
                </dl>
                {channelId !== null &&
                    updateTextsUrl !== null &&
                    editableTextFields.length > 0 && (
                    <WhatsAppProfileTextForm
                        key={JSON.stringify(data)}
                        updateTextsUrl={updateTextsUrl}
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
