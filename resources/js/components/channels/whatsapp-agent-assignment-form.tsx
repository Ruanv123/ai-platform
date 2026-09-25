import { useForm } from '@inertiajs/react';
import { updateAgent } from '@/routes/channels/whatsapp';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AgentOption } from '@/components/channels/whatsapp-channel-types';

type Props = {
    channelId: string;
    agents: AgentOption[];
    selectedAgentId: string | null;
    canManage: boolean;
};

export default function WhatsAppAgentAssignmentForm({
    channelId,
    agents,
    selectedAgentId,
    canManage,
}: Props) {
    const form = useForm({ agent_id: selectedAgentId ?? '' });
    const agentId = form.data.agent_id || 'none';

    if (!canManage) {
        return (
            <p className="text-muted-foreground text-sm">
                Somente Owner e Admin alteram o Agente vinculado.
            </p>
        );
    }

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(updateAgent(channelId).url, {
                    preserveScroll: true,
                });
            }}
        >
            <Select
                value={agentId}
                onValueChange={(value) =>
                    form.setData('agent_id', value === 'none' ? '' : value)
                }
            >
                <SelectTrigger id="whatsapp_agent_id">
                    <SelectValue placeholder="Nenhum Agente" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Nenhum Agente</SelectItem>
                    {agents.map((agent) => (
                        <SelectItem key={agent.id} value={agent.id}>
                            {agent.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {form.errors.agent_id && (
                <p className="text-destructive text-sm">
                    {form.errors.agent_id}
                </p>
            )}
            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        agentId === (selectedAgentId ?? 'none')
                    }
                >
                    {form.processing ? 'Salvando...' : 'Salvar Agente'}
                </Button>
            </div>
        </form>
    );
}
