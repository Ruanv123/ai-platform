import {
    ArrowLeft,
    Building2,
    Camera,
    EllipsisVertical,
    Mic,
    Paperclip,
    Phone,
    Smile,
} from 'lucide-react';
import type { TemplateDraft } from './template-draft';
import WhatsappCarouselCard from './whatsapp-carousel-card';
import WhatsappMessageBubble from './whatsapp-message-bubble';

export default function WhatsappPreview({ draft }: { draft: TemplateDraft }) {
    return (
        <div className="mx-auto flex max-w-100 flex-col overflow-hidden rounded-3xl border font-[system-ui] shadow-sm">
            <div className="flex items-center gap-2.5 bg-[#008069] px-3 py-2.5 text-white">
                <ArrowLeft className="size-5" />
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#dfe5e7] text-[#54656f]">
                    <Building2 className="size-5" />
                </span>
                <span className="grid min-w-0 flex-1 leading-tight">
                    <span className="font-medium">Sua empresa</span>
                    <span className="text-[12.5px] text-white/80">
                        Conta comercial
                    </span>
                </span>
                <Phone className="size-5" />
                <EllipsisVertical className="size-5" />
            </div>

            <div className="flex min-h-105 flex-col gap-2 bg-[#efeae2] px-3.5 pt-3 pb-4">
                <span className="self-center rounded-lg bg-white px-3 py-1 text-[12.5px] text-[#54656f] shadow-[0_1px_0.5px_rgba(11,20,26,0.13)]">
                    Hoje
                </span>
                <span className="max-w-[90%] self-center rounded-lg bg-[#ffeecd] px-3 py-1.5 text-center text-[12.5px] leading-snug text-[#54656f] shadow-[0_1px_0.5px_rgba(11,20,26,0.13)]">
                    Esta empresa usa um serviço seguro da Meta para gerenciar
                    esta conversa.
                </span>
                <WhatsappMessageBubble draft={draft} />
                {draft.format === 'carousel' && (
                    <div className="-mx-1 flex gap-2 overflow-x-auto px-3 pb-1.5">
                        {draft.cards.map((card) => (
                            <WhatsappCarouselCard
                                key={card.key}
                                card={card}
                                mediaKind={draft.cardHeaderFormat}
                                withBody={draft.cardsHaveBody}
                                buttonTypes={draft.cardButtonTypes}
                            />
                        ))}
                    </div>
                )}
                {draft.format === 'call_permission' && (
                    <div className="ml-2 grid w-[86%] gap-2.5 self-start rounded-lg bg-white p-3 shadow-[0_1px_0.5px_rgba(11,20,26,0.13)]">
                        <span className="flex items-center gap-2 text-sm font-semibold text-[#111b21]">
                            <Phone className="size-4.5 text-[#008069]" />
                            Permitir ligações desta empresa?
                        </span>
                        <span className="flex gap-2 text-sm font-medium">
                            <span className="flex h-9 flex-1 items-center justify-center rounded-full border border-[#e9edef] text-[#027eb5]">
                                Agora não
                            </span>
                            <span className="flex h-9 flex-1 items-center justify-center rounded-full bg-[#008069] text-white">
                                Permitir
                            </span>
                        </span>
                    </div>
                )}
            </div>

            <div className="flex items-center gap-1.5 bg-[#f0f2f5] px-2 py-1.5">
                <div className="flex h-10.5 min-w-0 flex-1 items-center gap-2.5 rounded-full bg-white px-3 text-[#8696a0]">
                    <Smile className="size-5" />
                    <span className="flex-1 text-[15px]">Mensagem</span>
                    <Paperclip className="size-5" />
                    <Camera className="size-5" />
                </div>
                <span className="flex size-10.5 shrink-0 items-center justify-center rounded-full bg-[#008069] text-white">
                    <Mic className="size-5" />
                </span>
            </div>
        </div>
    );
}
