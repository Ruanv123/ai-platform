import { Copy, ExternalLink, Phone, Reply } from 'lucide-react';
import type { ButtonType } from './template-draft';

const icons = {
    quick_reply: Reply,
    url: ExternalLink,
    phone_number: Phone,
    copy_code: Copy,
};

export default function WhatsappPreviewButton({
    type,
    text,
}: {
    type: ButtonType;
    text: string;
}) {
    const Icon = icons[type];

    return (
        <div className="flex h-11 items-center justify-center gap-1.5 border-t border-[#e9edef] text-sm font-medium text-[#027eb5]">
            <Icon className="size-4" />
            <span className="truncate">{text}</span>
        </div>
    );
}
