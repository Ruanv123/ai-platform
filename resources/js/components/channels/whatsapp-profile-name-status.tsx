import { Badge } from '@/components/ui/badge';

type Props = {
    status: string | null;
};

type NameStatusDetails = {
    label: string;
    variant: 'success' | 'secondary' | 'destructive' | 'outline';
    classification: 'approved' | 'other';
};

const nameStatusDetails: Record<string, NameStatusDetails> = {
    APPROVED: {
        label: 'Aprovado',
        variant: 'success',
        classification: 'approved',
    },
    AVAILABLE_WITHOUT_REVIEW: {
        label: 'Aprovado',
        variant: 'success',
        classification: 'approved',
    },
    DECLINED: {
        label: 'Recusado',
        variant: 'destructive',
        classification: 'other',
    },
    EXPIRED: {
        label: 'Expirado',
        variant: 'secondary',
        classification: 'other',
    },
    NONE: {
        label: 'Sem pedido',
        variant: 'outline',
        classification: 'other',
    },
    PENDING: {
        label: 'Em revisão',
        variant: 'secondary',
        classification: 'other',
    },
    PENDING_REVIEW: {
        label: 'Em revisão',
        variant: 'secondary',
        classification: 'other',
    },
};

export function isApprovedDisplayNameStatus(status: string | null): boolean {
    if (status === null) {
        return false;
    }

    return (
        nameStatusDetails[status.toUpperCase()]?.classification === 'approved'
    );
}

export default function WhatsAppProfileNameStatus({ status }: Props) {
    if (status === null) {
        return null;
    }

    const statusDetails = nameStatusDetails[status.toUpperCase()] ?? {
        label: 'Status não reconhecido',
        variant: 'outline' as const,
        classification: 'other' as const,
    };

    return <Badge variant={statusDetails.variant}>{statusDetails.label}</Badge>;
}
