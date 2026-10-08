import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export default function EditorToolbarButton({
    label,
    onApply,
    children,
}: {
    label: string;
    onApply: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-8"
            aria-label={label}
            title={label}
            onMouseDown={(event) => event.preventDefault()}
            onClick={onApply}
        >
            {children}
        </Button>
    );
}
