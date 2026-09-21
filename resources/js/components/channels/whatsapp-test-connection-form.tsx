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
import { store } from '@/routes/channels/whatsapp-test';

type AgentOption = {
    id: string;
    name: string;
};

export type WhatsAppTestChannel = {
    id: string;
    name: string;
    qrcode: string | null;
    agent: { id: string; name: string } | null;
};

type Props = {
    agents: AgentOption[];
    canManage: boolean;
    onSuccess?: () => void;
};

/** Creates a WhatsApp test Channel: names the integration, Evolution Go does the rest. */
export function WhatsAppTestConnectionForm({
    agents,
    canManage,
    onSuccess,
}: Props) {
    const [agentId, setAgentId] = useState('none');

    if (!canManage) {
        return (
            <p className="text-muted-foreground text-sm">
                Somente Owner e Admin alteram este Canal.
            </p>
        );
    }

    return (
        <Form {...store.form()} className="grid gap-5" onSuccess={onSuccess}>
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nome da integração</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue=""
                            placeholder="Ex.: desenvolvimento"
                            autoFocus
                        />
                        <p className="text-muted-foreground text-xs">
                            Só pra você identificar esta integração na listagem.
                        </p>
                        <InputError message={errors.name} />
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
                            {processing ? 'Criando...' : 'Criar e conectar'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
