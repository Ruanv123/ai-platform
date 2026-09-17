import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, update } from '@/routes/company';
import type { CompanyOption, Team } from '@/types';

type CompanyTeam = Pick<
    Team,
    | 'id'
    | 'name'
    | 'segment'
    | 'website'
    | 'businessDescription'
    | 'productsServices'
    | 'targetAudience'
    | 'phone'
    | 'publicEmail'
    | 'address'
>;

type Props = {
    team: CompanyTeam;
    segments: CompanyOption[];
    canUpdate: boolean;
};

export default function Company({ team, segments, canUpdate }: Props) {
    return (
        <>
            <Head title="Empresa" />

            <h1 className="sr-only">Empresa</h1>

            {canUpdate ? (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Dados da Empresa"
                        description="Mantenha as informações do seu negócio atualizadas"
                    />

                    <Form {...update.form()} className="space-y-8">
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nome fantasia</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        data-test="team-name-input"
                                        defaultValue={team.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="segment">Segmento</Label>
                                    <select
                                        id="segment"
                                        name="segment"
                                        defaultValue={team.segment ?? ''}
                                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                        required
                                    >
                                        <option value="">
                                            Selecione um segmento
                                        </option>
                                        {segments.map((segment) => (
                                            <option
                                                key={segment.value}
                                                value={segment.value}
                                            >
                                                {segment.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.segment} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="website">Site</Label>
                                    <Input
                                        id="website"
                                        name="website"
                                        type="url"
                                        defaultValue={team.website ?? ''}
                                        placeholder="https://exemplo.com.br"
                                    />
                                    <InputError message={errors.website} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="business_description">
                                        Descrição do negócio
                                    </Label>
                                    <textarea
                                        id="business_description"
                                        name="business_description"
                                        defaultValue={
                                            team.businessDescription ?? ''
                                        }
                                        required
                                        className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                    />
                                    <InputError
                                        message={errors.business_description}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="products_services">
                                        Produtos e serviços
                                    </Label>
                                    <textarea
                                        id="products_services"
                                        name="products_services"
                                        defaultValue={
                                            team.productsServices ?? ''
                                        }
                                        required
                                        className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                    />
                                    <InputError
                                        message={errors.products_services}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="target_audience">
                                        Público-alvo
                                    </Label>
                                    <textarea
                                        id="target_audience"
                                        name="target_audience"
                                        defaultValue={team.targetAudience ?? ''}
                                        required
                                        className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                    />
                                    <InputError
                                        message={errors.target_audience}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">
                                            Telefone público
                                        </Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            defaultValue={team.phone ?? ''}
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="public_email">
                                            E-mail público
                                        </Label>
                                        <Input
                                            id="public_email"
                                            name="public_email"
                                            type="email"
                                            defaultValue={
                                                team.publicEmail ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.public_email}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Endereço</Label>
                                    <textarea
                                        id="address"
                                        name="address"
                                        defaultValue={team.address ?? ''}
                                        className="border-input bg-background min-h-20 rounded-md border px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        data-test="team-save-button"
                                        disabled={processing}
                                    >
                                        Salvar alterações
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            ) : (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Dados da Empresa"
                        description="Você pode visualizar os dados da Empresa, mas apenas Proprietário e Administrador podem editá-los."
                    />

                    <div className="grid gap-5 rounded-xl border p-5 text-sm">
                        <CompanyDetail
                            label="Nome fantasia"
                            value={team.name}
                        />
                        <CompanyDetail
                            label="Segmento"
                            value={
                                segments.find(
                                    (segment) => segment.value === team.segment,
                                )?.label ?? team.segment
                            }
                        />
                        <CompanyDetail
                            label="Site"
                            value={team.website || 'Não informado'}
                        />
                        <CompanyDetail
                            label="Descrição do negócio"
                            value={team.businessDescription}
                            preserveWhitespace
                        />
                        <CompanyDetail
                            label="Produtos e serviços"
                            value={team.productsServices}
                            preserveWhitespace
                        />
                        <CompanyDetail
                            label="Público-alvo"
                            value={team.targetAudience}
                            preserveWhitespace
                        />
                        <CompanyDetail
                            label="Telefone público"
                            value={team.phone || 'Não informado'}
                        />
                        <CompanyDetail
                            label="E-mail público"
                            value={team.publicEmail || 'Não informado'}
                        />
                        <CompanyDetail
                            label="Endereço"
                            value={team.address || 'Não informado'}
                            preserveWhitespace
                        />
                    </div>
                </div>
            )}
        </>
    );
}

function CompanyDetail({
    label,
    value,
    preserveWhitespace = false,
}: {
    label: string;
    value?: string | null;
    preserveWhitespace?: boolean;
}) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p
                className={
                    preserveWhitespace ? 'whitespace-pre-wrap' : 'font-medium'
                }
            >
                {value || 'Não informado'}
            </p>
        </div>
    );
}

Company.layout = {
    breadcrumbs: [
        {
            title: 'Empresa',
            href: edit(),
        },
    ],
};
