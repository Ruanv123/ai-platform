import { Form, Head } from '@inertiajs/react';
import { Building2, ExternalLink } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import shopifyInstall from '@/routes/shopify/install';

type ShopSummary = {
    domain: string;
    name: string;
};

type TeamOption = {
    id: number;
    name: string;
    role: 'owner' | 'admin';
    shop: ShopSummary | null;
};

type Props = {
    shop: ShopSummary;
    teams: TeamOption[];
};

export default function ShopifyInstall({ shop, teams }: Props) {
    return (
        <main className="bg-background text-foreground flex min-h-screen justify-center px-5 py-10 sm:px-8">
            <Head title="Conectar loja Shopify" />
            <section className="flex w-full max-w-xl flex-col gap-7">
                <header className="flex flex-col gap-3">
                    <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-xl">
                        <Building2 className="size-5" aria-hidden="true" />
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Conectar {shop.name}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Escolha a Empresa que vai receber o catálogo de{' '}
                        {shop.domain}.
                    </p>
                </header>

                <Form
                    {...shopifyInstall.store.form()}
                    disableWhileProcessing
                    className="bg-card flex flex-col gap-6 rounded-xl border p-6 shadow-sm"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-3">
                                <Label htmlFor="team_id">Empresa</Label>
                                <select
                                    id="team_id"
                                    name="team_id"
                                    required
                                    defaultValue={teams[0]?.id ?? ''}
                                    disabled={teams.length === 0}
                                    className="border-input bg-background focus-visible:ring-ring h-10 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                                >
                                    <option value="" disabled>
                                        Selecione uma Empresa
                                    </option>
                                    {teams.map((team) => (
                                        <option key={team.id} value={team.id}>
                                            {team.name} ·{' '}
                                            {team.role === 'owner'
                                                ? 'Proprietário'
                                                : 'Administrador'}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.team_id} />
                            </div>

                            {teams.length === 0 && (
                                <p className="bg-muted/60 text-muted-foreground rounded-lg p-4 text-sm">
                                    Nenhuma Empresa está disponível. Apenas
                                    Proprietários e Administradores podem
                                    conectar uma loja.
                                </p>
                            )}

                            {teams.some((team) => team.shop !== null) && (
                                <p className="text-muted-foreground text-sm">
                                    Uma Empresa com outra loja exibirá os
                                    detalhes e pedirá confirmação antes da
                                    substituição.
                                </p>
                            )}

                            <Button
                                type="submit"
                                disabled={processing || teams.length === 0}
                            >
                                {processing ? 'Conectando...' : 'Continuar'}
                                <ExternalLink className="size-4" />
                            </Button>
                        </>
                    )}
                </Form>
            </section>
        </main>
    );
}
