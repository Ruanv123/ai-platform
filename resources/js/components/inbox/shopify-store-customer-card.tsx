export type StoreCustomerSummary = {
    id: string;
    name: string | null;
    email: string | null;
    totalSpent: string;
    currencyCode: string | null;
};

export type StoreOrderSummary = {
    id: string;
    orderNumber: string;
    totalAmount: string;
    currencyCode: string;
    financialStatus: string | null;
    fulfillmentStatus: string | null;
    orderedAt: string | null;
    attributed: boolean;
};

export default function ShopifyStoreCustomerCard({
    customer,
    orders,
}: {
    customer: StoreCustomerSummary | null;
    orders: StoreOrderSummary[];
}) {
    if (customer === null && orders.length === 0) {
        return null;
    }

    return (
        <section className="border-border/70 border-b px-4.5 py-3.5">
            <div className="text-muted-foreground mb-2.5 text-[10px] font-bold tracking-[0.07em] uppercase">
                {customer === null ? 'Pedidos da loja' : 'Cliente da loja'}
            </div>
            {customer !== null ? (
                <div className="grid gap-2">
                    <div className="text-xs font-semibold">
                        {customer.name ?? customer.email ?? 'Cliente da loja'}
                    </div>
                    {customer.name !== null && customer.email !== null ? (
                        <div className="text-muted-foreground text-xs">
                            {customer.email}
                        </div>
                    ) : null}
                    <div className="flex items-baseline justify-between gap-3 text-xs">
                        <span className="text-muted-foreground">
                            Total gasto
                        </span>
                        <span className="font-medium">
                            {formatCurrency(
                                customer.totalSpent,
                                customer.currencyCode,
                            )}
                        </span>
                    </div>
                </div>
            ) : null}
            <div className="mt-3 grid gap-2">
                <div className="text-muted-foreground text-[10px] font-bold tracking-[0.07em] uppercase">
                    Últimos pedidos
                </div>
                {orders.length === 0 ? (
                    <div className="text-muted-foreground text-xs">
                        Nenhum pedido espelhado.
                    </div>
                ) : (
                    <ul className="grid gap-2">
                        {orders.map((order) => (
                            <li
                                key={order.id}
                                className="border-border/70 grid gap-1 rounded-lg border p-2.5"
                            >
                                <div className="flex items-baseline justify-between gap-2 text-xs">
                                    <div className="flex min-w-0 items-center gap-1.5">
                                        <span className="font-semibold">
                                            {order.orderNumber}
                                        </span>
                                        {order.attributed ? (
                                            <span className="bg-primary/10 text-primary shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold">
                                                Venda atribuída
                                            </span>
                                        ) : null}
                                    </div>
                                    <span className="text-muted-foreground shrink-0">
                                        {formatDate(order.orderedAt)}
                                    </span>
                                </div>
                                <div className="flex items-baseline justify-between gap-2 text-[11px]">
                                    <span className="text-muted-foreground truncate">
                                        {formatStatus(order.financialStatus)} ·{' '}
                                        {formatStatus(order.fulfillmentStatus)}
                                    </span>
                                    <span className="shrink-0 font-medium">
                                        {formatCurrency(
                                            order.totalAmount,
                                            order.currencyCode,
                                        )}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

function formatCurrency(amount: string, currencyCode: string | null): string {
    const value = Number(amount);

    if (!Number.isFinite(value) || currencyCode === null) {
        return amount;
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: currencyCode,
    }).format(value);
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function formatStatus(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const labels: Record<string, string> = {
        AUTHORIZED: 'Autorizado',
        FULFILLED: 'Enviado',
        IN_PROGRESS: 'Em andamento',
        PAID: 'Pago',
        PARTIALLY_FULFILLED: 'Parcialmente enviado',
        PARTIALLY_PAID: 'Parcialmente pago',
        PENDING: 'Pendente',
        REFUNDED: 'Reembolsado',
        UNFULFILLED: 'Não enviado',
        VOIDED: 'Cancelado',
    };

    return labels[value] ?? value.toLowerCase().replaceAll('_', ' ');
}
