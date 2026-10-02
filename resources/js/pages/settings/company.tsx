import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import CompanyDetail from '@/components/company-detail';
import SiteReadProposalReview from '@/components/site-read-proposal-review';
import SiteReadPanel, {
    type LatestSiteRead,
} from '@/components/site-read-panel';
import SiteReadPolling from '@/components/site-read-polling';
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
    | 'brandVoice'
    | 'brandVoiceExamples'
> & { agentPauseDurationMinutes: number };

type Props = {
    team: CompanyTeam;
    segments: CompanyOption[];
    canUpdate: boolean;
    siteRead: {
        website: string | null;
        hasNewWebsite: boolean;
        canStart: boolean;
        availableAgainAt: string | null;
        latest: LatestSiteRead | null;
    };
    proposal: {
        id: number;
        fields: Record<
            string,
            {
                label: string;
                current: unknown;
                proposed: unknown;
                sourceUrl: string;
            }
        >;
    } | null;
};

export default function Company({
    team,
    segments,
    canUpdate,
    siteRead,
    proposal,
}: Props) {
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

                                <div className="space-y-4 border-t pt-6">
                                    <Heading
                                        variant="small"
                                        title="Voz da marca"
                                        description="Descreva como sua marca fala. O Estilo de cada Agente continua valendo quando houver divergência."
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="brand_voice">
                                            Descrição
                                        </Label>
                                        <textarea
                                            id="brand_voice"
                                            name="brand_voice"
                                            defaultValue={team.brandVoice ?? ''}
                                            maxLength={1000}
                                            className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm"
                                        />
                                        <InputError
                                            message={errors.brand_voice}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="brand_voice_examples_0">
                                            Frases de exemplo
                                        </Label>
                                        <p className="text-muted-foreground text-sm">
                                            Informe de 2 a 4 frases ou deixe
                                            todos os campos vazios.
                                        </p>
                                        {Array.from(
                                            { length: 4 },
                                            (_, index) => (
                                                <div
                                                    key={index}
                                                    className="grid gap-2"
                                                >
                                                    <Label
                                                        htmlFor={`brand_voice_examples_${index}`}
                                                    >
                                                        Frase de exemplo{' '}
                                                        {index + 1}
                                                    </Label>
                                                    <textarea
                                                        id={`brand_voice_examples_${index}`}
                                                        name="brand_voice_examples[]"
                                                        defaultValue={
                                                            team
                                                                .brandVoiceExamples?.[
                                                                index
                                                            ] ?? ''
                                                        }
                                                        maxLength={1000}
                                                        className="border-input bg-background min-h-16 rounded-md border px-3 py-2 text-sm"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `brand_voice_examples.${index}`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ),
                                        )}
                                        <InputError
                                            message={
                                                errors.brand_voice_examples
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Prazo da Pausa da IA</Label>
                                    <p className="text-muted-foreground text-sm">
                                        O prazo reinicia quando uma mensagem
                                        chega ou é enviada com sucesso durante o
                                        atendimento humano.
                                    </p>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="agent_pause_duration_hours">
                                                Horas
                                            </Label>
                                            <Input
                                                id="agent_pause_duration_hours"
                                                name="agent_pause_duration_hours"
                                                type="number"
                                                min="0"
                                                max="24"
                                                step="1"
                                                defaultValue={Math.floor(
                                                    team.agentPauseDurationMinutes /
                                                        60,
                                                )}
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.agent_pause_duration_hours
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="agent_pause_duration_minutes_part">
                                                Minutos
                                            </Label>
                                            <Input
                                                id="agent_pause_duration_minutes_part"
                                                name="agent_pause_duration_minutes_part"
                                                type="number"
                                                min="0"
                                                max="59"
                                                step="1"
                                                defaultValue={
                                                    team.agentPauseDurationMinutes %
                                                    60
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.agent_pause_duration_minutes_part
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.agent_pause_duration_minutes
                                                }
                                            />
                                        </div>
                                    </div>
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
                        <CompanyDetail
                            label="Voz da marca"
                            value={team.brandVoice}
                            preserveWhitespace
                        />
                        <CompanyDetail
                            label="Frases de exemplo"
                            value={team.brandVoiceExamples?.join('\n')}
                            preserveWhitespace
                        />
                        <CompanyDetail
                            label="Prazo da Pausa da IA"
                            value={`${Math.floor(team.agentPauseDurationMinutes / 60)} h ${team.agentPauseDurationMinutes % 60} min`}
                        />
                    </div>
                </div>
            )}
            <SiteReadProposalReview proposal={proposal} canUpdate={canUpdate} />
            <SiteReadPanel
                website={siteRead.website}
                hasNewWebsite={siteRead.hasNewWebsite}
                canStart={siteRead.canStart}
                canUpdate={canUpdate}
                availableAgainAt={siteRead.availableAgainAt}
                latest={siteRead.latest}
            />
            {canUpdate && siteRead.latest?.status === 'in_progress' ? (
                <SiteReadPolling key={siteRead.latest.id} />
            ) : null}
        </>
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
