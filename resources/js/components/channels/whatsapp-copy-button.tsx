import { Check, Clipboard } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    value: string;
    label: string;
};

export default function WhatsAppCopyButton({ value, label }: Props) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? <Check /> : <Clipboard />}
            {copied ? 'Copiado' : label}
        </Button>
    );
}
