import { Image, Play } from 'lucide-react';
import { useMemo } from 'react';
import { buttonTypeLabels } from './template-draft';
import type { CardButtonType, CardDraft } from './template-draft';
import WhatsappPreviewButton from './whatsapp-preview-button';
import { fillVariables } from './whatsapp-text';

export default function WhatsappCarouselCard({
    card,
    mediaKind,
    withBody,
    buttonTypes,
}: {
    card: CardDraft;
    mediaKind: 'IMAGE' | 'VIDEO';
    withBody: boolean;
    buttonTypes: CardButtonType[];
}) {
    const imageUrl = useMemo(
        () =>
            card.media && card.media.type.startsWith('image/')
                ? URL.createObjectURL(card.media)
                : null,
        [card.media],
    );

    return (
        <div className="flex w-52 shrink-0 flex-col overflow-hidden rounded-lg bg-white shadow-[0_1px_0.5px_rgba(11,20,26,0.13)]">
            <div className="px-1 pt-1">
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt="Mídia do card"
                        className="h-32 w-full rounded-md object-cover"
                    />
                ) : mediaKind === 'VIDEO' ? (
                    <div className="flex h-32 items-center justify-center rounded-md bg-[#3b4a54] text-white">
                        <Play className="size-7 fill-current" />
                    </div>
                ) : (
                    <div className="flex h-32 items-center justify-center rounded-md bg-[#d1d7db] text-[#8696a0]">
                        <Image className="size-8" />
                    </div>
                )}
            </div>
            {withBody && (
                <span className="px-2.5 pt-1.5 pb-2 text-sm leading-[19px] text-[#111b21]">
                    {card.body.trim() === ''
                        ? 'Texto do card'
                        : fillVariables(card.body, card.bodyExamples)}
                </span>
            )}
            {buttonTypes.map((type, index) => (
                <WhatsappPreviewButton
                    key={index}
                    type={type}
                    text={card.buttons[index]?.text || buttonTypeLabels[type]}
                />
            ))}
        </div>
    );
}
