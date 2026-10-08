import { cn } from '@/lib/utils';

export default function ChoiceCard({
    name,
    value,
    checked,
    label,
    description,
    onSelect,
}: {
    name: string;
    value: string;
    checked: boolean;
    label: string;
    description: string;
    onSelect: () => void;
}) {
    return (
        <label
            className={cn(
                'bg-card relative flex cursor-pointer flex-col gap-1.5 rounded-lg border p-3.5 shadow-xs transition-[border-color,box-shadow]',
                'has-[:focus-visible]:ring-ring/50 has-[:focus-visible]:ring-[3px]',
                checked && 'border-primary ring-primary ring-1',
            )}
        >
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                onChange={onSelect}
                className="sr-only"
            />
            <span className="flex items-center justify-between gap-2">
                <span className="text-sm font-semibold">{label}</span>
                <span
                    aria-hidden="true"
                    className={cn(
                        'bg-background size-4 shrink-0 rounded-full border',
                        checked
                            ? 'border-primary border-[5px]'
                            : 'border-input',
                    )}
                />
            </span>
            <span className="text-muted-foreground text-[13px] leading-[18px]">
                {description}
            </span>
        </label>
    );
}
