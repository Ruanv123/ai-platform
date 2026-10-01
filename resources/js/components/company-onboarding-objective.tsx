import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { objective as selectObjective } from '@/routes/onboarding/company';

type AgentObjectiveOption = {
    value: string;
    label: string;
    description: string;
};

type Props = {
    name: string;
    isSitePath: boolean;
    objectives: AgentObjectiveOption[];
};

export default function CompanyOnboardingObjective({
    name,
    isSitePath,
    objectives,
}: Props) {
    return (
        <>
            <Head title="Escolha o objetivo do Agente" />

            <main className="mx-auto grid min-h-screen max-w-3xl content-center gap-8 px-4 py-10 sm:px-6">
                <div className="grid gap-3">
                    <p className="text-primary text-sm font-medium">
                        {isSitePath ? '4 de 4' : '3 de 3'} · Primeiro Agente
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        Qual será o objetivo do primeiro Agente da {name}?
                    </h1>
                    <p className="text-muted-foreground max-w-2xl text-pretty">
                        Vamos preparar um ponto de partida. Você poderá ajustar
                        as instruções no Playground antes de publicar o Agente.
                    </p>
                </div>

                <Form {...selectObjective.form()} className="grid gap-6">
                    {({ errors, processing }) => (
                        <>
                            <fieldset className="grid gap-3">
                                <legend className="sr-only">
                                    Objetivo do primeiro Agente
                                </legend>
                                {objectives.map((item, index) => (
                                    <label
                                        key={item.value}
                                        className="bg-card has-checked:border-primary has-checked:bg-primary/5 grid cursor-pointer grid-cols-[auto_1fr] items-start gap-4 rounded-xl border p-5 transition-colors"
                                    >
                                        <input
                                            type="radio"
                                            name="objective"
                                            value={item.value}
                                            required
                                            defaultChecked={index === 0}
                                            className="accent-primary mt-1 size-4"
                                        />
                                        <span className="grid gap-1">
                                            <span className="font-medium">
                                                {item.label}
                                            </span>
                                            <span className="text-muted-foreground text-sm text-pretty">
                                                {item.description}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                                <InputError message={errors.objective} />
                            </fieldset>

                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                                className="w-full sm:w-fit"
                            >
                                {processing ? (
                                    <>
                                        <Spinner />
                                        Preparando seu Agente
                                    </>
                                ) : (
                                    'Criar meu Agente e abrir o Playground'
                                )}
                            </Button>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}
