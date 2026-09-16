import { useEffect } from 'react';

export function useAgentPlaygroundDirty(
    agentId: number | null,
    isDirty: boolean,
): void {
    useEffect(() => {
        if (agentId === null) {
            return;
        }

        window.localStorage.setItem(
            `agent-playground-dirty-${agentId}`,
            String(isDirty),
        );
    }, [agentId, isDirty]);
}
