import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Radio } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/integrations';

type Event = {
    id: string;
    name: string;
    description: string | null;
    emissionsCount: number;
};

export default function EventsPage({ events }: { events: Event[] }) {
    return (
        <>
            <Head title="Eventos" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <div className="flex items-start gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={index()}>
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Heading
                        title="Eventos declarados"
                        description="Nomes estáveis para métricas e webhooks."
                    />
                </div>
                <div className="grid gap-3">
                    {events.map((event) => (
                        <Card key={event.id}>
                            <CardContent className="flex items-center gap-3 p-4">
                                <Radio className="text-primary size-4" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold">
                                        {event.name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {event.description ?? 'Sem descrição'}
                                    </p>
                                </div>
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {event.emissionsCount} emitidos
                                </span>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
