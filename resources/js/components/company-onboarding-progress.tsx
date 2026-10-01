import { Form, Head, usePoll } from '@inertiajs/react';
import { continueMethod } from '@/routes/onboarding/company';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type SiteRead = {
    status: 'in_progress' | 'completed' | 'failed';
    statusLabel: string;
    foundPagesCount: number;
    readPagesCount: number;
    failedPagesCount: number;
    errorMessage: string | null;
};

type Props = {
    siteRead: SiteRead | null;
    teamName: string;
};

export default function CompanyOnboardingProgress({
    siteRead,
    teamName,
}: Props) {
    usePoll(
        3000,
        { only: ['step', 'siteRead', 'proposal', 'manualError'] },
        { mode: 'rest' },
    );

    const foundCount = siteRead?.foundPagesCount ?? 0;
    const readCount = siteRead?.readPagesCount ?? 0;
    const failedCount = siteRead?.failedPagesCount ?? 0;
    const processedCount = readCount + failedCount;
    const progress =
        foundCount > 0
            ? Math.min(100, Math.round((processedCount / foundCount) * 100))
            : 4;

    return (
        <>
            <Head title="Lendo seu site" />

            <main className="mx-auto grid min-h-screen max-w-3xl content-center gap-8 px-4 py-10 sm:px-6">
                <div className="grid gap-3">
                    <p className="text-primary text-sm font-medium">
                        2 de 3 · Leitura do site
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        Estamos conhecendo a {teamName}
                    </h1>
                    <p className="text-muted-foreground max-w-2xl text-pretty">
                        Mapeamos as páginas que ajudam a explicar sua empresa e
                        estamos lendo cada uma delas.
                    </p>
                </div>

                <section
                    className="bg-card grid gap-6 rounded-xl border p-5 sm:p-7"
                    aria-live="polite"
                    data-test="onboarding-site-read-progress"
                >
                    <div className="flex items-center gap-3">
                        <Spinner />
                        <div className="grid gap-1">
                            <h2 className="font-medium">
                                {siteRead?.status === 'completed'
                                    ? 'Organizando as informações'
                                    : 'Leitura em andamento'}
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                {siteRead?.statusLabel ??
                                    'Preparando a leitura'}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-3">
                        <div
                            className="bg-muted h-2 overflow-hidden rounded-full"
                            role="progressbar"
                            aria-label="Progresso da leitura do site"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={progress}
                        >
                            <div
                                className="bg-primary h-full rounded-full transition-[width] duration-300"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <div className="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <p>
                                <span className="text-foreground font-medium">
                                    {foundCount}
                                </span>{' '}
                                páginas encontradas
                            </p>
                            <p>
                                <span className="text-foreground font-medium">
                                    {readCount}
                                </span>{' '}
                                lidas
                            </p>
                            {failedCount > 0 ? (
                                <p>
                                    <span className="text-foreground font-medium">
                                        {failedCount}
                                    </span>{' '}
                                    com falha
                                </p>
                            ) : null}
                        </div>
                    </div>

                    {siteRead?.errorMessage ? (
                        <p className="text-destructive text-sm" role="alert">
                            {siteRead.errorMessage}
                        </p>
                    ) : null}

                    <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-muted-foreground text-sm">
                            Você pode continuar usando a plataforma enquanto a
                            Leitura termina.
                        </p>
                        <Form action={continueMethod.url()} method="post">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Continuando…'
                                        : 'Continuar sem esperar'}
                                </Button>
                            )}
                        </Form>
                    </div>
                </section>
            </main>
        </>
    );
}
