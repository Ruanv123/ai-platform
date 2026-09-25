import { lazy, Suspense, useRef, useState, type ChangeEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { ImageUp } from 'lucide-react';
import { Button } from '@/components/ui/button';

const WhatsAppProfilePhotoCropDialog = lazy(
    () => import('@/components/channels/whatsapp-profile-photo-crop-dialog'),
);

const maximumPhotoSize = 5 * 1024 * 1024;

type Props = {
    profilePictureUrl: string | null;
    appId: string | null;
    requiresAppId: boolean;
    connectionUrl: string;
    updateProfilePictureUrl: string | null;
    canEdit: boolean;
};

export default function WhatsAppProfilePhotoBlock({
    profilePictureUrl,
    appId,
    requiresAppId,
    connectionUrl,
    updateProfilePictureUrl,
    canEdit,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [imageUrl, setImageUrl] = useState<string | null>(null);
    const [validationError, setValidationError] = useState<string | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    function releaseSelectedImage(): void {
        if (imageUrl !== null) {
            URL.revokeObjectURL(imageUrl);
        }

        setSelectedFile(null);
        setImageUrl(null);
    }

    function selectPhoto(event: ChangeEvent<HTMLInputElement>): void {
        const file = event.currentTarget.files?.[0] ?? null;
        event.currentTarget.value = '';

        if (file === null) {
            return;
        }

        setValidationError(null);
        setServerError(null);

        if (file.type !== 'image/jpeg' && file.type !== 'image/png') {
            setValidationError('Escolha uma imagem JPEG ou PNG.');
            return;
        }

        if (file.size > maximumPhotoSize) {
            setValidationError('A imagem não pode ultrapassar 5 MB.');
            return;
        }

        releaseSelectedImage();
        setSelectedFile(file);
        setImageUrl(URL.createObjectURL(file));
    }

    function uploadPhoto(photo: File): void {
        if (updateProfilePictureUrl === null) {
            return;
        }

        releaseSelectedImage();
        setValidationError(null);
        setServerError(null);

        router.post(
            updateProfilePictureUrl,
            { photo },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) =>
                    setServerError(errors.photo ?? 'Não foi possível enviar a foto.'),
                onSuccess: () => setServerError(null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    const errorMessage = validationError ?? serverError;
    const hasAppId = appId !== null && appId.trim() !== '';
    const canUploadPhoto = canEdit && (!requiresAppId || hasAppId);

    return (
        <section className="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-center">
            <div className="bg-muted size-20 overflow-hidden rounded-full border">
                {profilePictureUrl !== null ? (
                    <img
                        src={profilePictureUrl}
                        alt="Foto do Perfil do WhatsApp"
                        className="size-full object-cover"
                    />
                ) : (
                    <div
                        aria-label="Foto do Perfil indisponível"
                        className="size-full"
                    />
                )}
            </div>

            <div className="grid justify-items-start gap-2">
                <div className="grid gap-1">
                    <p className="text-sm font-medium">Foto do Perfil</p>
                    <p className="text-muted-foreground text-xs">
                        JPEG ou PNG, até 5 MB.
                    </p>
                </div>

                {canUploadPhoto && updateProfilePictureUrl !== null && (
                    <>
                        <input
                            ref={inputRef}
                            type="file"
                            accept="image/jpeg,image/png"
                            aria-label="Escolher foto"
                            className="sr-only"
                            onChange={selectPhoto}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => inputRef.current?.click()}
                        >
                            <ImageUp />
                            {processing ? 'Enviando…' : 'Trocar foto'}
                        </Button>
                    </>
                )}

                {canEdit && requiresAppId && !hasAppId && (
                    <div
                        role="status"
                        className="bg-muted grid gap-1 rounded-md p-3 text-sm"
                    >
                        <p>Informe o App ID da Meta para trocar a foto.</p>
                        <Link
                            href={connectionUrl}
                            className="text-primary w-fit font-medium underline-offset-4 hover:underline"
                        >
                            Ir para Conexão
                        </Link>
                    </div>
                )}

                {errorMessage !== null && (
                    <p role="alert" className="text-destructive text-sm">
                        {errorMessage}
                    </p>
                )}
            </div>

            {selectedFile !== null && imageUrl !== null && (
                <Suspense
                    fallback={
                        <p role="status" className="text-muted-foreground text-sm">
                            Preparando o recorte da foto…
                        </p>
                    }
                >
                    <WhatsAppProfilePhotoCropDialog
                        imageUrl={imageUrl}
                        onCancel={releaseSelectedImage}
                        onConfirm={uploadPhoto}
                    />
                </Suspense>
            )}
        </section>
    );
}
