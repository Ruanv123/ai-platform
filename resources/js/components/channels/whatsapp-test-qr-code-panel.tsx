import { usePoll } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import type { WhatsAppTestChannelDetails } from '@/components/channels/whatsapp-test-channel-types';

type Props = {
    whatsappTest: WhatsAppTestChannelDetails;
};

function logQrCodeLoaded(event: SyntheticEvent<HTMLImageElement>): void {
    const image = event.currentTarget;
    const mimeType = /^data:(image\/[^;,]+);base64,/i.exec(image.currentSrc)?.[1] ?? null;

    console.info('[WHATSAPP-TEST-QR] image.loaded', {
        mime_type: mimeType,
        width: image.naturalWidth,
        height: image.naturalHeight,
        source_length: image.currentSrc.length,
    });
}

function logQrCodeFailed(event: SyntheticEvent<HTMLImageElement>): void {
    const image = event.currentTarget;
    const mimeType = /^data:(image\/[^;,]+);base64,/i.exec(image.currentSrc)?.[1] ?? null;

    console.error('[WHATSAPP-TEST-QR] image.failed', {
        mime_type: mimeType,
        source_length: image.currentSrc.length,
    });
}

export default function WhatsAppTestQrCodePanel({ whatsappTest }: Props) {
    usePoll(2000, { only: ['whatsappTest'] });

    return (
        <div className="grid gap-4">
            {whatsappTest.status === 'error' &&
                whatsappTest.lastError !== null && (
                    <p className="text-destructive text-sm">
                        {whatsappTest.lastError}
                    </p>
                )}

            <div className="bg-muted flex aspect-square w-full max-w-64 items-center justify-center justify-self-center overflow-hidden rounded-lg border">
                {whatsappTest.qrcode !== null ? (
                    <img
                        src={whatsappTest.qrcode}
                        alt="QR code de pareamento do WhatsApp"
                        className="size-full object-contain"
                        onLoad={logQrCodeLoaded}
                        onError={logQrCodeFailed}
                    />
                ) : (
                    <div className="size-3/4 animate-pulse rounded-md bg-black/10" />
                )}
            </div>

            <p className="text-muted-foreground text-center text-sm">
                {whatsappTest.qrcode !== null
                    ? 'No WhatsApp do aparelho de testes, abra Aparelhos conectados → Conectar um aparelho e escaneie o código acima.'
                    : 'Aguardando o Evolution Go gerar o QR code...'}
            </p>
        </div>
    );
}
