import { Image, Play, Tag } from 'lucide-react';
import { useMemo } from 'react';
import FormattedWhatsappText from './formatted-whatsapp-text';
import { previewBody, previewButtons, previewFooter } from './template-draft';
import type { TemplateDraft } from './template-draft';
import WhatsappPreviewButton from './whatsapp-preview-button';
import { fillVariables } from './whatsapp-text';

export default function WhatsappMessageBubble({
    draft,
}: {
    draft: TemplateDraft;
}) {
    const headerType = draft.headerType;
    const showsHeader =
        draft.format === 'standard' ||
        draft.format === 'coupon' ||
        draft.format === 'limited_time_offer';
    const imageUrl = useMemo(
        () =>
            draft.headerMedia && draft.headerMedia.type.startsWith('image/')
                ? URL.createObjectURL(draft.headerMedia)
                : null,
        [draft.headerMedia],
    );
    const footer = previewFooter(draft);

    return (
        <div className="relative ml-2 w-[86%] self-start rounded-lg rounded-tl-none bg-white shadow-[0_1px_0.5px_rgba(11,20,26,0.13)]">
            <svg
                width="8"
                height="13"
                viewBox="0 0 8 13"
                aria-hidden="true"
                className="absolute top-0 -left-2"
            >
                <path
                    d="M1.533 3.568 8 12.193V1H2.812C1.042 1 .474 2.156 1.533 3.568z"
                    fill="#ffffff"
                />
            </svg>
            <div className="px-1 pt-1">
                {showsHeader &&
                    headerType === 'IMAGE' &&
                    (imageUrl ? (
                        <img
                            src={imageUrl}
                            alt="Imagem do cabeçalho"
                            className="h-44 w-full rounded-md object-cover"
                        />
                    ) : (
                        <div className="flex h-40 items-center justify-center rounded-md bg-[#d1d7db] text-[#8696a0]">
                            <Image className="size-10" />
                        </div>
                    ))}
                {showsHeader && headerType === 'VIDEO' && (
                    <div className="flex h-40 items-center justify-center rounded-md bg-[#3b4a54] text-white">
                        <span className="flex size-12 items-center justify-center rounded-full bg-black/45">
                            <Play className="size-5 fill-current" />
                        </span>
                    </div>
                )}
                {showsHeader && headerType === 'DOCUMENT' && (
                    <div className="flex items-center gap-2.5 rounded-md bg-[#f5f6f6] p-2.5">
                        <span className="flex h-9 w-7.5 shrink-0 items-end justify-center rounded-sm bg-[#e5484d] pb-1 text-[9px] font-bold text-white">
                            DOC
                        </span>
                        <span className="truncate text-sm text-[#111b21]">
                            {draft.headerMedia?.name ??
                                draft.existingHeaderMediaName ??
                                'documento.pdf'}
                        </span>
                    </div>
                )}
                {draft.format === 'limited_time_offer' && (
                    <div className="mt-1 flex items-center gap-2.5 rounded-md bg-[#f0f2f5] p-2.5">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#d9fdd3] text-[#008069]">
                            <Tag className="size-4.5" />
                        </span>
                        <span className="grid min-w-0 leading-tight">
                            <span className="text-[15px] font-bold text-[#111b21]">
                                {draft.offerText || 'Título da oferta'}
                            </span>
                            {draft.offerHasExpiration && (
                                <span className="text-[12.5px] text-[#667781]">
                                    A oferta termina em 05:42:10
                                </span>
                            )}
                            <span className="text-[12.5px] text-[#667781]">
                                Código {draft.offerCode || 'CODIGO'}
                            </span>
                        </span>
                    </div>
                )}
            </div>
            <div className="flex flex-col gap-0.5 px-2 pt-1 pb-1.5">
                {showsHeader &&
                    headerType === 'TEXT' &&
                    draft.headerText.trim() !== '' && (
                        <span className="text-[15px] leading-5 font-bold text-[#111b21]">
                            {fillVariables(
                                draft.headerText,
                                draft.headerExamples,
                            )}
                        </span>
                    )}
                <span className="text-[14.2px] leading-[19px] break-words whitespace-pre-wrap text-[#111b21]">
                    <FormattedWhatsappText
                        text={previewBody(draft)}
                        placeholder="Sua mensagem aparece aqui."
                    />
                </span>
                {footer.trim() !== '' && (
                    <span className="mt-1 text-[13px] leading-[17px] text-[#667781]">
                        {footer}
                    </span>
                )}
                <span className="self-end text-[11px] text-[#667781]">
                    10:24
                </span>
            </div>
            {previewButtons(draft).map((button, index) => (
                <WhatsappPreviewButton
                    key={index}
                    type={button.type}
                    text={button.text}
                />
            ))}
        </div>
    );
}
