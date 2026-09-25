import { Badge } from '@/components/ui/badge';

type Props = {
    status: string | null;
};

const nameStatusLabels: Record<
    string,
    {
        label: string;
        variant: 'success' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    APPROVED: { label: 'Aprovado', variant: 'success' },
    AVAILABLE_WITHOUT_REVIEW: { label: 'Aprovado', variant: 'success' },
    DECLINED: { label: 'Recusado', variant: 'destructive' },
    EXPIRED: { label: 'Expirado', variant: 'secondary' },
    NONE: { label: 'Sem pedido', variant: 'outline' },
    PENDING: { label: 'Em revisão', variant: 'secondary' },
    PENDING_REVIEW: { label: 'Em revisão', variant: 'secondary' },
};

export function isApprovedDisplayNameStatus(status: string | null): boolean {
    const normalizedStatus = status?.toUpperCase();

    return (
        normalizedStatus === 'APPROVED' ||
        normalizedStatus === 'AVAILABLE_WITHOUT_REVIEW'
    );
}

export default function WhatsAppProfileNameStatus({ status }: Props) {
    if (status === null) {
        return null;
    }

    const statusDetails = nameStatusLabels[status.toUpperCase()] ?? {
        label: 'Status não reconhecido',
        variant: 'outline' as const,
    };

    return <Badge variant={statusDetails.variant}>{statusDetails.label}</Badge>;
}
