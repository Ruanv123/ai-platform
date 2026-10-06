# Loja espelhada em modelo canônico, com adapter por Provedor de e-commerce

Status: superseded by ADR-0018

A plataforma mantém uma cópia local da Loja — Produtos, Variantes, Categorias, Clientes da loja e Pedidos — num modelo canônico único, igual para qualquer Provedor de e-commerce. Cada provedor entra por um adapter com contrato fixo (conectar, importar tudo, buscar um recurso pelo id externo, traduzir para o canônico, validar webhook), e o Agente só enxerga o canônico. É o caminho oposto ao do ADR 0004, e de propósito: lá os provedores divergem em semântica sem tradução limpa; aqui produto, variante e pedido têm correspondência direta, e o Agente, a busca semântica e os Fluxos precisam de uma forma só.

## Considered Options

- **Consultar a API do provedor na hora**: rejeitada porque a busca semântica do catálogo precisa de embeddings locais, e porque cada chamada do Agente passaria a depender da latência e do rate limit de terceiros.
- **Strategies por provedor sem canônico, como no ADR 0004**: rejeitada porque multiplicaria as Ações do Agente e a busca de catálogo por provedor.

## Consequences

- O webhook é só um aviso de que um recurso mudou: o job busca o estado atual na API do provedor e faz upsert no canônico. Ordem de chegada, duplicata e formato do payload deixam de importar — a Nuvemshop e a VTEX mandam só o id, e nenhum provedor garante ordem. Uma reconciliação periódica recupera o que se perdeu.
- Nem o webhook nem o último payload do recurso são guardados; só o id externo e as colunas canônicas. Campo que não foi promovido a coluna não existe para a plataforma.
- A defesa contra divergência é a mesma do ADR 0004: uma suíte de conformidade com fixtures reais de cada provedor. Na v1 só a Shopify está nela.
