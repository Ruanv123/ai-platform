import type { ReactNode } from 'react';
import InputError from '@/components/input-error';

type Props = {
    children: ReactNode;
    error?: string;
    id: string;
    label: string;
    sourceUrl?: string;
};

export default function OnboardingProposalField({
    children,
    error,
    id,
    label,
    sourceUrl,
}: Props) {
    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <label htmlFor={id} className="text-sm font-medium">
                    {label}
                </label>
                {sourceUrl ? (
                    <a
                        href={sourceUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary text-sm underline underline-offset-4"
                    >
                        Página de origem
                    </a>
                ) : (
                    <span className="text-muted-foreground text-xs">
                        Sem informação encontrada no site
                    </span>
                )}
            </div>
            {children}
            <InputError message={error} />
        </div>
    );
}
