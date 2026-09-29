import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import attendedContactRegistration from '@/routes/integrations/crm/attended-contact-registration';

type Props = {
    enabled: boolean;
    minimumMessages: number;
};

export default function CrmAttendedContactSettings({ enabled, minimumMessages }: Props) {
    const [isEnabled, setIsEnabled] = useState(enabled);
    const [messageCount, setMessageCount] = useState(String(minimumMessages));

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const parsedMessageCount = Number(messageCount);
        if (!Number.isInteger(parsedMessageCount) || parsedMessageCount < 1 || parsedMessageCount > 1000) {
            return;
        }

        router.put(
            attendedContactRegistration.update().url,
            { enabled: isEnabled, minimum_messages: parsedMessageCount },
            { preserveScroll: true },
        );
    };

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>Registrar Contatos atendidos</CardTitle>
                <CardDescription>
                    Depois de uma resposta enviada pelo Agente no WhatsApp, crie a Pessoa e o Vínculo no CRM quando o Contato atingir o mínimo de mensagens recebidas.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="grid gap-4">
                    <label className="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            checked={isEnabled}
                            onChange={(event) => setIsEnabled(event.target.checked)}
                            className="accent-primary mt-0.5 size-4"
                        />
                        <span>Ativar o registro automático de Contatos atendidos</span>
                    </label>
                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="minimum-attended-contact-messages">
                            Mínimo de mensagens recebidas
                        </Label>
                        <Input
                            id="minimum-attended-contact-messages"
                            type="number"
                            min={1}
                            max={1000}
                            value={messageCount}
                            onChange={(event) => setMessageCount(event.target.value)}
                            disabled={!isEnabled}
                        />
                    </div>
                    <Button type="submit" className="w-fit">
                        Salvar configuração
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
