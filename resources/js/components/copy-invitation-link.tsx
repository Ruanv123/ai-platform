import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { show as showInvitation } from '@/routes/invitations';

type Props = {
    code: string;
};

export default function CopyInvitationLink({ code }: Props) {
    const [copiedText, copy] = useClipboard();
    const [showManualCopy, setShowManualCopy] = useState(false);
    const invitationPath = showInvitation(code).url;
    const invitationLink =
        typeof window === 'undefined'
            ? invitationPath
            : new URL(invitationPath, window.location.origin).toString();
    const clipboardAvailable =
        typeof navigator !== 'undefined' && Boolean(navigator.clipboard);
    const isCopied = copiedText === invitationLink;
    const needsManualCopy = showManualCopy || !clipboardAvailable;

    const copyInvitationLink = async () => {
        const copied = await copy(invitationLink);

        if (copied) {
            toast.success('Link do convite copiado.');
        } else {
            setShowManualCopy(true);
        }
    };

    return (
        <div className="flex min-w-0 flex-col items-end gap-2">
            {!needsManualCopy ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="invitation-copy-link-button"
                    onClick={copyInvitationLink}
                >
                    {isCopied ? <Check /> : <Copy />}
                    {isCopied ? 'Link copiado' : 'Copiar link'}
                </Button>
            ) : null}

            {needsManualCopy ? (
                <Input
                    readOnly
                    value={invitationLink}
                    aria-label="Link do convite"
                    data-test="invitation-link"
                    onFocus={(event) => event.currentTarget.select()}
                    className="w-full min-w-64 text-xs"
                />
            ) : null}
        </div>
    );
}
