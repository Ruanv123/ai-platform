import { Form } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { store } from '@/actions/App/Http/Controllers/Settings/SiteReadController';
import SiteReadAgentLinks from '@/components/site-read-agent-links';

type AgentOption = {
    id: string;
    name: string;
};

type SiteReadPage = {
    id: number;
    url: string;
    title: string;
    status: 'pending' | 'read' | 'failed';
    errorMessage: string | null;
};

export type LatestSiteRead = {
    id: number;
    website: string;
    status: 'in_progress' | 'completed' | 'failed';
    statusLabel: string;
    startedAt: string;
    completedAt: string | null;
    foundPagesCount: number;
    readPagesCount: number;
    failedPagesCount: number;
    errorMessage: string | null;
    linkableAgents: AgentOption[];
    pages: SiteReadPage[];
};

type Props = {
    website: string | null;
    canStart: boolean;
    canUpdate: boolean;
    availableAgainAt: string | null;
    latest: LatestSiteRead | null;
};

const pageStatusLabels: Record<SiteReadPage['status'], string> = {
    pending: 'Aguardando',
    read: 'Lida',
    failed: 'Com falha',
};

export default function SiteReadPanel({
    website,
    canStart,
    canUpdate,
    availableAgainAt,
    latest,
}: Props) {
    return (
        <section className="space-y-5 border-t pt-6">
            <Heading
                variant="small"
                title="Leitura do site"
                description="Leia as páginas principais do site e acompanhe os Documentos criados na Base de conhecimento."
            />

            {latest ? (
                <div className="space-y-4 rounded-xl border p-5 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-medium">{latest.statusLabel}</p>
                            <p className="text-muted-foreground">
                                Iniciada em {latest.startedAt}
                                {latest.completedAt
                                    ? ` · concluída em ${latest.completedAt}`
                                    : ''}
                            </p>
                        </div>
                        <p className="text-muted-foreground">
                            {latest.readPagesCount} lidas ·{' '}
                            {latest.failedPagesCount} com falha ·{' '}
                            {latest.foundPagesCount} encontradas
                        </p>
                    </div>

                    {latest.errorMessage ? (
                        <p role="alert" className="text-destructive">
                            {latest.errorMessage}
                        </p>
                    ) : null}

                    {latest.pages.length > 0 ? (
                        <ul className="divide-y rounded-lg border">
                            {latest.pages.map((page) => (
                                <li
                                    key={page.id}
                                    className="grid gap-1 px-3 py-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {page.title}
                                        </p>
                                        <p className="text-muted-foreground truncate">
                                            {page.url}
                                        </p>
                                        {page.errorMessage ? (
                                            <p className="text-destructive mt-1">
                                                {page.errorMessage}
                                            </p>
                                        ) : null}
                                    </div>
                                    <span className="text-muted-foreground">
                                        {pageStatusLabels[page.status]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : latest.status === 'in_progress' ? (
                        <p className="text-muted-foreground">
                            Mapeando as páginas do site…
                        </p>
                    ) : null}

                    {latest.status === 'completed' &&
                    latest.linkableAgents.length > 0 ? (
                        <SiteReadAgentLinks
                            siteReadId={latest.id}
                            agents={latest.linkableAgents}
                        />
                    ) : null}
                </div>
            ) : (
                <p className="text-muted-foreground rounded-xl border p-5 text-sm">
                    Nenhuma Leitura do site foi iniciada.
                </p>
            )}

            {canUpdate ? (
                <div className="space-y-2">
                    <Form {...store.form()}>
                        {({ errors, processing }) => (
                            <div className="space-y-2">
                                <Button
                                    type="submit"
                                    disabled={!canStart || processing}
                                    data-test="site-read-start-button"
                                >
                                    {latest?.status === 'in_progress' ||
                                    processing
                                        ? 'Leitura em andamento'
                                        : 'Ler o site'}
                                </Button>
                                <InputError
                                    message={errors.site_read ?? errors.website}
                                />
                            </div>
                        )}
                    </Form>
                    {!website ? (
                        <p className="text-muted-foreground text-sm">
                            Informe o site da Empresa acima para iniciar a
                            Leitura.
                        </p>
                    ) : null}
                    {availableAgainAt &&
                    !canStart &&
                    latest?.status !== 'in_progress' ? (
                        <p className="text-muted-foreground text-sm">
                            Você poderá iniciar outra Leitura em{' '}
                            {availableAgainAt}.
                        </p>
                    ) : null}
                    <div className="sr-only" aria-live="polite">
                        {latest?.status === 'in_progress'
                            ? 'A Leitura do site está em andamento.'
                            : ''}
                    </div>
                </div>
            ) : (
                <p className="text-muted-foreground text-sm">
                    Apenas Proprietário e Administrador podem iniciar uma
                    Leitura do site.
                </p>
            )}
        </section>
    );
}
