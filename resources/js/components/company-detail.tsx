type Props = {
    label: string;
    value?: string | null;
    preserveWhitespace?: boolean;
};

export default function CompanyDetail({
    label,
    value,
    preserveWhitespace = false,
}: Props) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p
                className={
                    preserveWhitespace ? 'whitespace-pre-wrap' : 'font-medium'
                }
            >
                {value || 'Não informado'}
            </p>
        </div>
    );
}
