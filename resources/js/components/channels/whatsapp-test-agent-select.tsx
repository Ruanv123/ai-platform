import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/channels/whatsapp-test';
import type {
    AgentOption,
    WhatsAppTestChannelDetails,
} from '@/components/channels/whatsapp-test-channel-types';

type Props = {
    whatsappTest: WhatsAppTestChannelDetails;
    agents: AgentOption[];
};

export default function WhatsAppTestAgentSelect({
    whatsappTest,
    agents,
}: Props) {
    const [agentId, setAgentId] = useState(
        whatsappTest.agent?.id.toString() ?? 'none',
    );

    const onChange = (value: string) => {
        setAgentId(value);
        router.patch(update(whatsappTest.id).url, {
            agent_id: value === 'none' ? '' : value,
        });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="agent_id">Agente vinculado</Label>
            <Select value={agentId} onValueChange={onChange}>
                <SelectTrigger id="agent_id">
                    <SelectValue placeholder="Nenhum Agente" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Nenhum Agente</SelectItem>
                    {agents.map((agent) => (
                        <SelectItem key={agent.id} value={agent.id.toString()}>
                            {agent.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
