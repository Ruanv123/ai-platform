import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Bot,
    Check,
    Circle,
    FileCheck2,
    HardDrive,
    MessageCircle,
    MessagesSquare,
    Target,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type ChecklistItem = {
    key: string;
    label: string;
    description: string;
    completed: boolean;
    href: string;
};

type Summary = {
    agents: {
        active: number;
        total: number;
    };
    documents: {
        ready: number;
        failed: number;
    };
    storage: {
        usedBytes: number;
        limitBytes: number;
        usedPercentage: number;
    };
    whatsapp: {
        status: 'unverified' | 'connected' | 'error' | null;
        statusLabel: string;
    };
};

type Props = {
    checklist: ChecklistItem[];
    summary: Summary;
};

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const unitIndex = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** unitIndex;

    return `${new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: value >= 100 ? 0 : 1,
    }).format(value)} ${units[unitIndex]}`;
}

function ChecklistIcon({ completed }: { completed: boolean }) {
    return completed ? (
        <span className="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-full">
            <Check className="size-4" strokeWidth={2.5} />
        </span>
    ) : (
        <span className="border-muted-foreground/30 text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-full border">
            <Circle className="size-3 fill-current" />
        </span>
    );
}

function SummaryMetric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Bot;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="bg-muted/35 flex min-h-28 flex-col justify-between rounded-lg p-4">
            <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium">
                <Icon className="size-4" />
                <span>{label}</span>
            </div>
            <div className="flex items-baseline gap-2">
                <span className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </span>
                {detail && (
                    <span className="text-muted-foreground text-xs">
                        {detail}
                    </span>
                )}
            </div>
        </div>
    );
}

function WhatsAppStatus({ summary }: { summary: Summary }) {
    const statusClass =
        summary.whatsapp.status === 'connected'
            ? 'bg-emerald-500'
            : summary.whatsapp.status === 'error'
              ? 'bg-destructive'
              : 'bg-muted-foreground/50';

    return (
        <div className="bg-muted/35 flex min-h-28 flex-col justify-between rounded-lg p-4">
            <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium">
                <MessageCircle className="size-4" />
                <span>WhatsApp</span>
            </div>
            <div className="flex items-center gap-2">
                <span className={`size-2 rounded-full ${statusClass}`} />
                <span className="text-base font-semibold">
                    {summary.whatsapp.statusLabel}
                </span>
            </div>
        </div>
    );
}

export default function Dashboard({ checklist, summary }: Props) {
    const metrics = [
        { label: 'Conversas', icon: MessagesSquare },
        { label: 'Atendimentos resolvidos', icon: Check },
        { label: 'Transferências', icon: ArrowRight },
        { label: 'Oportunidades criadas', icon: Target },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <Heading
                    title="Dashboard"
                    description="Acompanhe a ativação e a saúde da sua Empresa."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b pb-5">
                            <div className="flex items-start justify-between gap-4">
                                <div className="space-y-1.5">
                                    <CardTitle>Ative seu atendimento</CardTitle>
                                    <CardDescription>
                                        Complete as etapas para colocar o
                                        atendimento no ar.
                                    </CardDescription>
                                </div>
                                <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                    <Bot className="size-5" />
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="divide-border divide-y">
                                {checklist.map((item) => (
                                    <Link
                                        key={item.key}
                                        href={item.href}
                                        prefetch
                                        className="group hover:bg-muted/35 focus-visible:bg-muted/35 focus-visible:ring-ring flex items-center gap-4 px-6 py-4 transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                    >
                                        <ChecklistIcon
                                            completed={item.completed}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={`block text-sm font-medium ${item.completed ? 'text-muted-foreground line-through' : ''}`}
                                            >
                                                {item.label}
                                            </span>
                                            <span className="text-muted-foreground mt-0.5 block text-xs">
                                                {item.description}
                                            </span>
                                        </span>
                                        <ArrowRight className="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-0.5" />
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-4">
                            <CardTitle>Resumo da conta</CardTitle>
                            <CardDescription>
                                Os números atuais da sua Empresa.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <SummaryMetric
                                icon={Bot}
                                label="Agentes"
                                value={`${summary.agents.active}/${summary.agents.total}`}
                                detail="ativos"
                            />
                            <SummaryMetric
                                icon={FileCheck2}
                                label="Documentos prontos"
                                value={summary.documents.ready.toString()}
                            />
                            <SummaryMetric
                                icon={AlertCircle}
                                label="Documentos com erro"
                                value={summary.documents.failed.toString()}
                            />
                            <WhatsAppStatus summary={summary} />
                            <div className="bg-muted/35 col-span-full rounded-lg p-4">
                                <div className="mb-3 flex items-center justify-between gap-4">
                                    <div className="text-muted-foreground flex items-center gap-2 text-xs font-medium">
                                        <HardDrive className="size-4" />
                                        <span>Armazenamento</span>
                                    </div>
                                    <span className="text-muted-foreground text-xs tabular-nums">
                                        {summary.storage.usedPercentage}%
                                    </span>
                                </div>
                                <div className="bg-background h-2 overflow-hidden rounded-full">
                                    <div
                                        className="bg-primary h-full rounded-full transition-[width] duration-300"
                                        style={{
                                            width: `${summary.storage.usedPercentage}%`,
                                        }}
                                    />
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    {formatBytes(summary.storage.usedBytes)} de{' '}
                                    {formatBytes(summary.storage.limitBytes)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <section
                    aria-labelledby="future-metrics-title"
                    className="space-y-4"
                >
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h2
                                id="future-metrics-title"
                                className="text-base font-semibold"
                            >
                                Métricas de atendimento
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Acompanhe os resultados do seu atendimento aqui.
                            </p>
                        </div>
                        <Badge variant="secondary">Em breve</Badge>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metrics.map(({ label, icon: Icon }) => (
                            <Card
                                key={label}
                                aria-disabled="true"
                                className="bg-muted/20 border-dashed shadow-none"
                            >
                                <CardHeader className="gap-3 pb-0">
                                    <div className="text-muted-foreground flex items-center justify-between gap-3">
                                        <Icon className="size-5" />
                                        <Badge variant="outline">
                                            Em breve
                                        </Badge>
                                    </div>
                                    <CardTitle className="text-sm font-medium">
                                        {label}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <span className="text-muted-foreground text-2xl font-semibold">
                                        —
                                    </span>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = () => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
});
