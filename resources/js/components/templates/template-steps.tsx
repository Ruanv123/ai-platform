import { Check } from 'lucide-react';
import { Fragment } from 'react';
import { cn } from '@/lib/utils';

export default function TemplateSteps({
    steps,
    current,
    onSelect,
}: {
    steps: string[];
    current: number;
    onSelect: (step: number) => void;
}) {
    return (
        <ol aria-label="Etapas da criação" className="flex items-center gap-3">
            {steps.map((label, index) => {
                const step = index + 1;
                const isDone = step < current;
                const isCurrent = step === current;

                return (
                    <Fragment key={label}>
                        <li>
                            <button
                                type="button"
                                onClick={() => onSelect(step)}
                                aria-current={isCurrent ? 'step' : undefined}
                                className="focus-visible:ring-ring/50 flex h-9 items-center gap-2 rounded-md px-1 outline-none focus-visible:ring-[3px]"
                            >
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                        isCurrent &&
                                            'border-primary bg-primary text-primary-foreground',
                                        isDone && 'border-primary text-primary',
                                        !isCurrent &&
                                            !isDone &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {isDone ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        step
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-sm whitespace-nowrap',
                                        isCurrent
                                            ? 'font-semibold'
                                            : 'font-medium',
                                        !isCurrent &&
                                            !isDone &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {label}
                                </span>
                            </button>
                        </li>
                        {step < steps.length && (
                            <li
                                aria-hidden="true"
                                className={cn(
                                    'h-px w-10',
                                    isDone ? 'bg-primary' : 'bg-border',
                                )}
                            />
                        )}
                    </Fragment>
                );
            })}
        </ol>
    );
}
