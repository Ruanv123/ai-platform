import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export default function WhatsAppProfileSkeleton() {
    return (
        <div className="grid gap-6" aria-label="Carregando Perfil do WhatsApp" role="status">
            <Card>
                <CardHeader className="gap-3">
                    <Skeleton className="h-5 w-44" />
                    <Skeleton className="h-4 w-72 max-w-full" />
                </CardHeader>
                <CardContent className="grid gap-5">
                    <div className="flex items-center gap-4">
                        <Skeleton className="size-20 rounded-full" />
                        <div className="grid flex-1 gap-2">
                            <Skeleton className="h-4 w-40 max-w-full" />
                            <Skeleton className="h-3 w-24 max-w-full" />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Skeleton className="h-20" />
                        <Skeleton className="h-20" />
                        <Skeleton className="h-20" />
                        <Skeleton className="h-20" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
