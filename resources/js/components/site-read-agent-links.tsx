import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { linkAgents } from '@/actions/App/Http/Controllers/Settings/SiteReadController';

type AgentOption = {
    id: string;
    name: string;
};

type Props = {
    siteReadId: number;
    agents: AgentOption[];
};

export default function SiteReadAgentLinks({ siteReadId, agents }: Props) {
    const form = useForm<{ agent_ids: string[] }>({ agent_ids: [] });

    function toggleAgent(agentId: string, isSelected: boolean): void {
        form.setData(
            'agent_ids',
            isSelected
                ? [...form.data.agent_ids, agentId]
                : form.data.agent_ids.filter(
                      (selectedId) => selectedId !== agentId,
                  ),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.post(linkAgents({ siteRead: siteReadId }).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-xl border p-5"
            data-test="site-read-agent-links"
        >
            <div className="space-y-1">
                <h3 className="font-medium">Ligar a Coleção do site</h3>
                <p className="text-muted-foreground text-sm">
                    A Coleção não foi ligada automaticamente aos Agentes
                    existentes. Escolha quais devem consultá-la.
                </p>
            </div>

            <fieldset disabled={form.processing} className="space-y-3">
                <legend className="sr-only">
                    Agentes que consultarão a Coleção do site
                </legend>
                {agents.map((agent) => (
                    <label
                        key={agent.id}
                        htmlFor={`site-read-agent-${agent.id}`}
                        className="hover:bg-muted/50 flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 py-1 text-sm"
                    >
                        <input
                            id={`site-read-agent-${agent.id}`}
                            type="checkbox"
                            checked={form.data.agent_ids.includes(agent.id)}
                            onChange={(event) =>
                                toggleAgent(agent.id, event.target.checked)
                            }
                            className="accent-primary focus-visible:outline-ring size-4"
                            data-test="site-read-agent-option"
                        />
                        <span>{agent.name}</span>
                    </label>
                ))}
            </fieldset>

            <InputError
                message={form.errors.agent_ids ?? form.errors['agent_ids.0']}
            />

            <Button
                type="submit"
                disabled={form.processing || form.data.agent_ids.length === 0}
            >
                {form.processing ? 'Ligando…' : 'Ligar aos Agentes escolhidos'}
            </Button>
        </form>
    );
}
