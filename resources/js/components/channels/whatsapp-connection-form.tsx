import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/channels/whatsapp';

type AgentOption = {
    id: string;
    name: string;
};

export type WhatsAppChannel = {
    id: string;
    wabaId: string | null;
    appId: string | null;
    phoneNumberId: string | null;
    accessTokenSet: boolean;
    accessTokenMasked: string | null;
    appSecretSet: boolean;
    appSecretMasked: string | null;
    agent: { id: string; name: string } | null;
};

type Props = {
    whatsapp: WhatsAppChannel | null;
    agents: AgentOption[];
    canManage: boolean;
    onSuccess?: () => void;
};

/** Credentials form for the WhatsApp official Channel, shared by its edit page and the creation dialog. */
export function WhatsAppConnectionForm({
    whatsapp,
    agents,
    canManage,
    onSuccess,
}: Props) {
    const [agentId, setAgentId] = useState(
        whatsapp?.agent?.id.toString() ?? 'none',
    );
    const isEditing = whatsapp !== null;

    if (!canManage) {
        return (
            <p className="text-muted-foreground text-sm">
                Somente Owner e Admin alteram este Canal.
            </p>
        );
    }

    return (
        <Form
            {...(isEditing ? update.form(whatsapp.id) : store.form())}
            className="grid gap-5"
            onSuccess={onSuccess}
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="waba_id">WABA ID</Label>
                            <Input
                                id="waba_id"
                                name="waba_id"
                                defaultValue={whatsapp?.wabaId ?? ''}
                                placeholder="Ex.: 102030405060708"
                            />
                            <InputError message={errors.waba_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="phone_number_id">
                                Phone Number ID
                            </Label>
                            <Input
                                id="phone_number_id"
                                name="phone_number_id"
                                defaultValue={whatsapp?.phoneNumberId ?? ''}
                                placeholder="ID exibido no WhatsApp Manager"
                            />
                            <InputError message={errors.phone_number_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="app_id">App ID</Label>
                            <Input
                                id="app_id"
                                name="app_id"
                                defaultValue={whatsapp?.appId ?? ''}
                                placeholder="ID do aplicativo da Meta"
                                maxLength={255}
                                required
                            />
                            <InputError message={errors.app_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="access_token">
                                Access token permanente
                            </Label>
                            <Input
                                id="access_token"
                                name="access_token"
                                type="password"
                                autoComplete="new-password"
                                placeholder={
                                    whatsapp?.accessTokenMasked ??
                                    'Cole o token permanente'
                                }
                            />
                            {whatsapp?.accessTokenSet && (
                                <p className="text-muted-foreground text-xs">
                                    Deixe vazio para manter o token atual.
                                </p>
                            )}
                            <InputError message={errors.access_token} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="app_secret">App Secret</Label>
                            <Input
                                id="app_secret"
                                name="app_secret"
                                type="password"
                                autoComplete="new-password"
                                placeholder={
                                    whatsapp?.appSecretMasked ??
                                    'Cole o App Secret'
                                }
                            />
                            {whatsapp?.appSecretSet && (
                                <p className="text-muted-foreground text-xs">
                                    Deixe vazio para manter o segredo atual.
                                </p>
                            )}
                            <InputError message={errors.app_secret} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="agent_id">Agente vinculado</Label>
                        <Select value={agentId} onValueChange={setAgentId}>
                            <SelectTrigger id="agent_id">
                                <SelectValue placeholder="Nenhum Agente" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Nenhum Agente
                                </SelectItem>
                                {agents.map((agent) => (
                                    <SelectItem
                                        key={agent.id}
                                        value={agent.id.toString()}
                                    >
                                        {agent.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="agent_id"
                            value={agentId === 'none' ? '' : agentId}
                        />
                        <InputError message={errors.agent_id} />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Salvando...'
                                : isEditing
                                  ? 'Salvar alterações'
                                  : 'Salvar credenciais'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
