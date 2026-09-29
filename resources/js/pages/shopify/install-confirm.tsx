import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import shopifyInstall from '@/routes/shopify/install';

type ShopSummary = {
    domain: string;
    name: string;
};

type Props = {
    shop: ShopSummary;
    team: {
        id: number;
        name: string;
    };
    existingShop: ShopSummary;
};

export default function ShopifyInstallConfirm({
    shop,
    team,
    existingShop,
}: Props) {
    return (
        <main className="bg-background text-foreground flex min-h-screen justify-center px-5 py-10 sm:px-8">
            <Head title="Confirmar troca de loja Shopify" />
            <section className="flex w-full max-w-xl flex-col gap-7">
                <header className="flex flex-col gap-3">
                    <div className="flex size-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                        <AlertTriangle className="size-5" aria-hidden="true" />
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Substituir a loja conectada?
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        A Empresa {team.name} já está conectada a{' '}
                        {existingShop.name} ({existingShop.domain}).
                    </p>
                </header>

                <div className="flex flex-col gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-5 text-sm">
                    <p className="font-medium">Ao continuar:</p>
                    <p>
                        {existingShop.domain} será desconectada e seu espelho de
                        catálogo será apagado.
                    </p>
                    <p>
                        {shop.domain} será conectada à Empresa {team.name}.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Form
                        {...shopifyInstall.store.form()}
                        disableWhileProcessing
                    >
                        <input type="hidden" name="team_id" value={team.id} />
                        <input
                            type="hidden"
                            name="confirm_replacement"
                            value="1"
                        />
                        <Button type="submit" variant="destructive">
                            Desconectar e conectar
                        </Button>
                    </Form>
                    <Button asChild variant="outline">
                        <Link href={shopifyInstall.resume.url()}>Voltar</Link>
                    </Button>
                </div>
            </section>
        </main>
    );
}
