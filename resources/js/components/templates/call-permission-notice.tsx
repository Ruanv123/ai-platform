import { Phone } from 'lucide-react';

export default function CallPermissionNotice() {
    return (
        <div className="bg-muted/50 flex items-start gap-2.5 rounded-lg border p-3">
            <Phone className="mt-0.5 size-4 shrink-0" />
            <div className="grid gap-0.5">
                <span className="text-sm font-medium">
                    O pedido de permissão é adicionado pelo WhatsApp
                </span>
                <span className="text-muted-foreground text-[13px]">
                    O Contato escolhe se permite ou não que a empresa ligue.
                    Esse formato não aceita cabeçalho, rodapé nem outros botões.
                </span>
            </div>
        </div>
    );
}
