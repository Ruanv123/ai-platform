import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import type { WhatsAppProfileData } from '@/components/channels/whatsapp-profile-types';
import WhatsAppProfileNameStatus, {
    isApprovedDisplayNameStatus,
} from '@/components/channels/whatsapp-profile-name-status';

type Props = {
    profile: WhatsAppProfileData;
    requestDisplayNameUrl: string | null;
    canRequestDisplayName: boolean;
    canEdit: boolean;
};

export default function WhatsAppProfileDisplayNameBlock({
    profile,
    requestDisplayNameUrl,
    canRequestDisplayName,
    canEdit,
}: Props) {
    const form = useForm({ name: '' });
    const hasRequest =
        profile.newDisplayName !== null || profile.newNameStatus !== null;
    const hasApprovedRequest = isApprovedDisplayNameStatus(
        profile.newNameStatus,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (
            requestDisplayNameUrl === null ||
            !canRequestDisplayName ||
            !canEdit ||
            form.data.name.trim() === ''
        ) {
            return;
        }

        form.post(requestDisplayNameUrl, { preserveScroll: true });
    }

    return (
        <section
            className="grid gap-4"
            aria-labelledby="profile-display-name-title"
        >
            <div className="grid gap-3">
                <h3
                    id="profile-display-name-title"
                    className="text-sm font-medium"
                >
                    Nome de exibição
                </h3>
                <dl className="grid gap-3 sm:grid-cols-2">
                    <div className="grid content-start gap-1">
                        <dt className="text-muted-foreground text-sm">
                            Nome atual
                        </dt>
                        <dd className="text-sm font-medium break-words">
                            {profile.displayName ?? '—'}
                        </dd>
                        {profile.nameStatus !== null && (
                            <dd>
                                <WhatsAppProfileNameStatus
                                    status={profile.nameStatus}
                                />
                            </dd>
                        )}
                    </div>
                    {hasRequest && (
                        <div className="grid content-start gap-1">
                            <dt className="text-muted-foreground text-sm">
                                Nome pedido
                            </dt>
                            <dd className="text-sm font-medium break-words">
                                {profile.newDisplayName ?? '—'}
                            </dd>
                            {profile.newNameStatus !== null && (
                                <dd>
                                    <WhatsAppProfileNameStatus
                                        status={profile.newNameStatus}
                                    />
                                </dd>
                            )}
                        </div>
                    )}
                </dl>
            </div>

            {hasApprovedRequest && (
                <Alert>
                    <AlertTitle>Próxima etapa</AlertTitle>
                    <AlertDescription>
                        Depois da aprovação, re-registre manualmente o número no
                        WhatsApp Manager para aplicar o novo nome.
                    </AlertDescription>
                </Alert>
            )}

            {canRequestDisplayName &&
                canEdit &&
                requestDisplayNameUrl !== null && (
                    <form className="grid gap-4" onSubmit={submit}>
                        <Alert>
                            <AlertTitle>Antes de enviar</AlertTitle>
                            <AlertDescription>
                                A Meta revisa o nome de exibição e aceita no
                                máximo 10 pedidos a cada 30 dias.
                            </AlertDescription>
                        </Alert>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-display-name-request">
                                Novo nome de exibição
                            </Label>
                            <Input
                                id="profile-display-name-request"
                                value={form.data.name}
                                maxLength={256}
                                aria-invalid={form.errors.name !== undefined}
                                aria-describedby={
                                    form.errors.name
                                        ? 'profile-display-name-error'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError
                                id="profile-display-name-error"
                                message={form.errors.name}
                            />
                        </div>
                        <div>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.name.trim() === ''
                                }
                            >
                                {form.processing
                                    ? 'Enviando pedido…'
                                    : 'Enviar pedido'}
                            </Button>
                        </div>
                    </form>
                )}
        </section>
    );
}
