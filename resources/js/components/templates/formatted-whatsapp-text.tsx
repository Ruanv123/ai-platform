import { cn } from '@/lib/utils';
import { formattedSegments } from './whatsapp-text';

export default function FormattedWhatsappText({
    text,
    placeholder,
}: {
    text: string;
    placeholder: string;
}) {
    if (text.trim() === '') {
        return <span className="text-[#667781]">{placeholder}</span>;
    }

    return (
        <>
            {formattedSegments(text).map((segment, index) => (
                <span
                    key={index}
                    className={cn(
                        segment.kind === 'bold' && 'font-bold',
                        segment.kind === 'italic' && 'italic',
                        segment.kind === 'strike' && 'line-through',
                        segment.kind === 'mono' && 'font-mono text-[13px]',
                    )}
                >
                    {segment.text}
                </span>
            ))}
        </>
    );
}
