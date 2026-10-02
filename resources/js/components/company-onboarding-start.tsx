import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/onboarding/company';

type Props = {
    name: string;
};

export default function CompanyOnboardingStart({ name }: Props) {
    return (
        <>
            <Head title="Configure sua Empresa" />

            <main className="mx-auto grid min-h-screen max-w-3xl content-center gap-8 px-4 py-10 sm:px-6">
                <div className="grid gap-3">
                    <p className="text-primary text-sm font-medium">
                        Sua Empresa
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        Comece pelo seu site
                    </h1>
                    <p className="text-muted-foreground max-w-2xl text-pretty">
                        Informe o nome e o site da Empresa. Vamos buscar os
                        dados do seu negócio para você revisar antes de usar.
                    </p>
                </div>

                <Form {...store.form()} className="grid gap-6">
                    {({ errors, processing }) => (
                        <section className="bg-card grid gap-5 rounded-xl border p-5 sm:p-7">
                            <div className="grid gap-2">
                                <label
                                    htmlFor="name"
                                    className="text-sm font-medium"
                                >
                                    Nome da Empresa
                                </label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    defaultValue={name}
                                    placeholder="Como sua empresa é conhecida?"
                                    autoComplete="organization"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <label
                                    htmlFor="website"
                                    className="text-sm font-medium"
                                >
                                    Site da Empresa
                                </label>
                                <Input
                                    id="website"
                                    name="website"
                                    type="url"
                                    placeholder="https://exemplo.com.br"
                                    autoComplete="url"
                                />
                                <p className="text-muted-foreground text-sm text-pretty">
                                    Instagram e links de WhatsApp não podem ser
                                    lidos. Você poderá preencher os dados à mão.
                                </p>
                                <InputError message={errors.website} />
                            </div>

                            <InputError message={errors.path} />

                            <div className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center">
                                <Button
                                    type="submit"
                                    name="path"
                                    value="site"
                                    size="lg"
                                    disabled={processing}
                                    className="sm:flex-1"
                                >
                                    {processing ? <Spinner /> : null}
                                    Ler meu site
                                </Button>
                                <Button
                                    type="submit"
                                    name="path"
                                    value="manual"
                                    variant="outline"
                                    size="lg"
                                    disabled={processing}
                                    formNoValidate
                                    className="sm:flex-1"
                                >
                                    Não tenho site
                                </Button>
                            </div>
                        </section>
                    )}
                </Form>
            </main>
        </>
    );
}
