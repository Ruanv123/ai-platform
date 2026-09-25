import { useState } from 'react';
import Cropper from 'react-easy-crop';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type CroppedArea = {
    x: number;
    y: number;
    width: number;
    height: number;
};

type Props = {
    imageUrl: string;
    onCancel: () => void;
    onConfirm: (photo: File) => void;
};

function createCroppedPhoto(imageUrl: string, area: CroppedArea): Promise<File> {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = 640;
            canvas.height = 640;
            const context = canvas.getContext('2d');

            if (context === null) {
                reject(new Error('Não foi possível preparar a foto.'));
                return;
            }

            context.drawImage(
                image,
                area.x,
                area.y,
                area.width,
                area.height,
                0,
                0,
                640,
                640,
            );
            canvas.toBlob(
                (blob) => {
                    if (blob === null) {
                        reject(new Error('Não foi possível preparar a foto.'));
                        return;
                    }

                    resolve(
                        new File([blob], 'whatsapp-profile.jpg', {
                            type: 'image/jpeg',
                        }),
                    );
                },
                'image/jpeg',
                0.92,
            );
        };
        image.onerror = () => reject(new Error('Não foi possível abrir a foto.'));
        image.src = imageUrl;
    });
}

export default function WhatsAppProfilePhotoCropDialog({
    imageUrl,
    onCancel,
    onConfirm,
}: Props) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedArea, setCroppedArea] = useState<CroppedArea | null>(null);
    const [croppedPhoto, setCroppedPhoto] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processingPreview, setProcessingPreview] = useState(false);

    function closeDialog(): void {
        if (previewUrl !== null) {
            URL.revokeObjectURL(previewUrl);
        }

        onCancel();
    }

    async function createPreview(): Promise<void> {
        if (croppedArea === null) {
            setError('Ajuste o recorte antes de continuar.');
            return;
        }

        setProcessingPreview(true);
        setError(null);

        try {
            const photo = await createCroppedPhoto(imageUrl, croppedArea);
            setCroppedPhoto(photo);
            setPreviewUrl(URL.createObjectURL(photo));
        } catch (caughtError) {
            setError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Não foi possível preparar a foto.',
            );
        } finally {
            setProcessingPreview(false);
        }
    }

    function returnToCrop(): void {
        if (previewUrl !== null) {
            URL.revokeObjectURL(previewUrl);
        }

        setCroppedPhoto(null);
        setPreviewUrl(null);
    }

    function confirmPhoto(): void {
        if (croppedPhoto === null) {
            return;
        }

        if (previewUrl !== null) {
            URL.revokeObjectURL(previewUrl);
        }

        onConfirm(croppedPhoto);
    }

    return (
        <Dialog open onOpenChange={(open) => !open && closeDialog()}>
            <DialogContent className="sm:max-w-xl data-[state=open]:zoom-in-100 data-[state=closed]:zoom-out-100">
                <DialogHeader>
                    <DialogTitle>Recortar foto do Perfil</DialogTitle>
                    <DialogDescription>
                        Arraste a imagem e ajuste o zoom. A prévia será enviada
                        como uma foto quadrada.
                    </DialogDescription>
                </DialogHeader>

                {previewUrl === null ? (
                    <div className="grid gap-4">
                        <div className="bg-muted relative h-72 overflow-hidden rounded-md">
                            <Cropper
                                image={imageUrl}
                                crop={crop}
                                zoom={zoom}
                                aspect={1}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={(_, area) => setCroppedArea(area)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <label
                                htmlFor="whatsapp-profile-photo-zoom"
                                className="text-sm font-medium"
                            >
                                Zoom
                            </label>
                            <input
                                id="whatsapp-profile-photo-zoom"
                                aria-label="Zoom"
                                type="range"
                                min={1}
                                max={3}
                                step={0.1}
                                value={zoom}
                                onChange={(event) =>
                                    setZoom(Number(event.target.value))
                                }
                                className="accent-primary w-full"
                            />
                        </div>
                    </div>
                ) : (
                    <div className="grid justify-items-center gap-3">
                        <img
                            src={previewUrl}
                            alt="Prévia da foto recortada"
                            className="size-48 rounded-full border object-cover"
                        />
                        <p className="text-muted-foreground text-sm">
                            Prévia da foto que será aplicada ao Perfil.
                        </p>
                    </div>
                )}

                {error !== null && (
                    <p role="alert" className="text-destructive text-sm">
                        {error}
                    </p>
                )}

                <DialogFooter>
                    {previewUrl === null ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeDialog}
                                disabled={processingPreview}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                onClick={createPreview}
                                disabled={processingPreview}
                            >
                                {processingPreview
                                    ? 'Preparando…'
                                    : 'Pré-visualizar'}
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={returnToCrop}
                            >
                                Voltar ao recorte
                            </Button>
                            <Button type="button" onClick={confirmPhoto}>
                                Confirmar foto
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
